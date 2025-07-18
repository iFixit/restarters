<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use App\Services\Auth\iFixitAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MigrateUserToExternal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:migrate-to-external 
                            {local_user_id : The local user ID to migrate}
                            {external_user_id : The external/iFixit user ID to link to}
                            {--dry-run : Preview the migration without making changes}
                            {--force : Force migration even if user already has external_user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate a local user to their iFixit counterpart by linking specific user IDs';

    private iFixitAuthService $ifixitService;

    /**
     * Create a new command instance.
     */
    public function __construct(iFixitAuthService $ifixitService)
    {
        parent::__construct();
        $this->ifixitService = $ifixitService;
    }

    /**
     * Get the console command description.
     */
    public function getDescription(): string
    {
        return $this->description . "\n\n" .
               "This command safely migrates a local user to link them with their iFixit account.\n" .
               "It requires both the local user ID and the external iFixit user ID to prevent\n" .
               "accidental account takeovers.\n\n" .
               "Examples:\n" .
               "  php artisan user:migrate-to-external 123 456789\n" .
               "  php artisan user:migrate-to-external 123 456789 --dry-run\n" .
               "  php artisan user:migrate-to-external 123 456789 --force\n\n" .
               "Options:\n" .
               "  --dry-run  Preview changes without applying them\n" .
               "  --force    Force migration even if user already has external_user_id";
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $localUserId = $this->argument('local_user_id');
        $externalUserId = $this->argument('external_user_id');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("Starting user migration for local user ID: {$localUserId} -> external user ID: {$externalUserId}");

        // Find the local user
        $localUser = User::find($localUserId);
        
        if (!$localUser) {
            $this->error("Local user with ID '{$localUserId}' not found.");
            return 1;
        }

        // Check if user already has external_user_id
        if (!$force && $localUser->external_user_id) {
            $this->error("User already has external_user_id: {$localUser->external_user_id}. Use --force to override.");
            return 1;
        }

        $this->info("Found local user: {$localUser->name} (ID: {$localUser->id}, Email: {$localUser->email})");

        // Get external user data from iFixit
        $externalUserData = $this->ifixitService->getUserById($externalUserId);
        
        if (!$externalUserData) {
            $this->error("Could not find iFixit user with ID '{$externalUserId}'.");
            return 1;
        }

        // Validate that the fetched user ID matches the requested external user ID
        if ($externalUserData['userid'] != $externalUserId) {
            $this->error("External user ID mismatch. Expected: {$externalUserId}, Got: {$externalUserData['userid']}");
            return 1;
        }

        $this->info("Found iFixit user: {$externalUserData['username']} (ID: {$externalUserData['userid']})");

        // Display what will be updated
        $this->displayMigrationPreview($localUser, $externalUserData);

        if ($dryRun) {
            $this->info("Dry run mode - no changes made.");
            return 0;
        }

        // Confirm the migration
        if (!$force && !$this->confirm('Do you want to proceed with the migration?')) {
            $this->info("Migration cancelled.");
            return 0;
        }

        // Perform the migration
        return $this->performMigration($localUser, $externalUserData);
    }

    /**
     * Display what will be changed during migration
     */
    private function displayMigrationPreview(User $localUser, array $externalUserData): void
    {
        $this->info("\n=== Migration Preview ===");
        
        $changes = [];
        
        // Name
        if ($localUser->name !== $externalUserData['username']) {
            $changes[] = "Name: '{$localUser->name}' → '{$externalUserData['username']}'";
        }
        
        // External user ID
        if ($localUser->external_user_id !== $externalUserData['userid']) {
            $changes[] = "External User ID: " . ($localUser->external_user_id ?: 'null') . " → '{$externalUserData['userid']}'";
        }
        
        // External username
        $newExternalUsername = $externalUserData['unique_username'] ?? null;
        if ($localUser->external_username !== $newExternalUsername) {
            $changes[] = "External Username: " . ($localUser->external_username ?: 'null') . " → " . ($newExternalUsername ?: 'null');
        }
        
        // Username
        if ($localUser->username !== $newExternalUsername) {
            $changes[] = "Username: " . ($localUser->username ?: 'null') . " → " . ($newExternalUsername ?: 'null');
        }
        
        // Role mapping
        $newRole = Role::RESTARTER; // Default role for external users
        if (isset($externalUserData['greatest_privilege']) && $externalUserData['greatest_privilege'] === 'Admin') {
            $newRole = Role::ADMINISTRATOR;
        }
        
        if ($localUser->role !== $newRole) {
            $changes[] = "Role: {$localUser->role} → {$newRole}";
        }

        if (empty($changes)) {
            $this->info("No changes needed - user data is already synchronized.");
        } else {
            $this->info("Changes to be made:");
            foreach ($changes as $change) {
                $this->line("  - {$change}");
            }
        }
    }

    /**
     * Perform the actual migration
     */
    private function performMigration(User $localUser, array $externalUserData): int
    {
        try {
            // Prepare the update data
            $updateData = [
                'name' => $externalUserData['username'],
                'external_user_id' => $externalUserData['userid'],
                'external_username' => $externalUserData['unique_username'] ?? null,
                'username' => $externalUserData['unique_username'] ?? null,
            ];

            // Map role from iFixit privilege level
            $role = Role::RESTARTER; // Default role for external users
            if (isset($externalUserData['greatest_privilege']) && $externalUserData['greatest_privilege'] === 'Admin') {
                $role = Role::ADMINISTRATOR;
            }
            $updateData['role'] = $role;

            // Update the user
            $localUser->update($updateData);

            // Generate username if not provided
            if (!$localUser->username) {
                $localUser->generateAndSetUsername();
                $localUser->save();
            }

            $this->info("✓ Migration completed successfully!");
            $this->info("User '{$localUser->email}' is now linked to iFixit user ID: {$externalUserData['userid']}");

            // Log the migration
            Log::info('User migrated to external', [
                'local_user_id' => $localUser->id,
                'email' => $localUser->email,
                'external_user_id' => $externalUserData['userid'],
                'external_username' => $externalUserData['unique_username'] ?? null
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("Migration failed: {$e->getMessage()}");
            
            Log::error('User migration to external failed', [
                'local_user_id' => $localUser->id,
                'email' => $localUser->email,
                'external_user_data' => $externalUserData,
                'error' => $e->getMessage()
            ]);

            return 1;
        }
    }
} 