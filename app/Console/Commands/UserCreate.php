<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Services\DiscourseService;
use App\Models\User;
use App\WikiSyncStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create {name} {email} {password} {language} {repair_network_id} 
                           {--role=4 : Role ID (1=ROOT, 2=ADMINISTRATOR, 3=HOST, 4=RESTARTER, 5=GUEST, 6=NETWORK_COORDINATOR)}
                           {--consent-past-data= : Consent date for past data in YYYY-MM-DD format, defaults to today}
                           {--consent-future-data= : Consent date for future data in YYYY-MM-DD format, defaults to today}
                           {--consent-gdpr= : Consent date for GDPR in YYYY-MM-DD format, defaults to today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a user with specified attributes.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(DiscourseService $discourseService): void
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->argument('password');
        $language = $this->argument('language');
        $repair_network_id = $this->argument('repair_network_id');
        $role = $this->option('role');
        
        // Get today's date for default consent values
        $today = Carbon::today()->format('Y-m-d');
        
        // Get consent dates from options or use today if not set
        $consent_past_data = $this->option('consent-past-data') ?: $today;
        $consent_future_data = $this->option('consent-future-data') ?: $today;
        $consent_gdpr = $this->option('consent-gdpr') ?: $today;

        // Validate consent dates
        foreach ([
            'consent_past_data' => $consent_past_data,
            'consent_future_data' => $consent_future_data,
            'consent_gdpr' => $consent_gdpr,
        ] as $label => $date) {
            if (!$this->validateDate($date)) {
                $this->error("Invalid date for $label: $date. Expected format: YYYY-MM-DD");
                return;
            }
        }

        if (User::where('email', $email)->count() > 0)
        {
            $this->info("User $email already exists - leaving unmodified");
            return;
        }

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ];

        $validator = Validator::make([
                                         'name' => $name,
                                         'email' => $email,
                                         'password' => $password,
                                     ], $rules);

        if ($validator->fails())
        {
            $this->error("Invalid parameters " . $validator->messages()->toJson());
        } else
        {
            $userData = [
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => $role,
                'recovery' => substr(bin2hex(openssl_random_pseudo_bytes(32)), 0, 24),
                'recovery_expires' => strftime('%Y-%m-%d %X', time() + (24 * 60 * 60)),
                'calendar_hash' => Str::random(15),
                'username' => '',
                'wiki_sync_status' => WikiSyncStatus::CreateAtLogin,
                'language' => $language,
                'repair_network' => $repair_network_id,
            ];

            // Add consent data if provided
            if (!empty($consent_past_data)) {
                $userData['consent_past_data'] = $consent_past_data;
            }
            
            if (!empty($consent_future_data)) {
                $userData['consent_future_data'] = $consent_future_data;
            }
            
            if (!empty($consent_gdpr)) {
                $userData['consent_gdpr'] = $consent_gdpr;
            }

            $user = User::create($userData);

            if ($user)
            {
                $this->info("User created #" . $user->id);
                $this->info("Role: " . $this->getRoleName($user->role));
                
                if (!empty($userData['consent_past_data']) || !empty($userData['consent_future_data']) || !empty($userData['consent_gdpr'])) {
                    $this->info("Consent dates set:");
                    if (!empty($userData['consent_past_data'])) {
                        $this->info("- Past data: {$userData['consent_past_data']}");
                    }
                    if (!empty($userData['consent_future_data'])) {
                        $this->info("- Future data: {$userData['consent_future_data']}");
                    }
                    if (!empty($userData['consent_gdpr'])) {
                        $this->info("- GDPR: {$userData['consent_gdpr']}");
                    }
                }

                if (config('restarters.features.discourse_integration')) {
                    $user->generateAndSetUsername();
                    $discourseService->syncSso($user);
                }
            } else
            {
                $this->error("User creation failed");
            }

            $user->generateAndSetUsername();
        }
    }

    /**
     * Get the role name from the role ID.
     *
     * @param int $roleId
     * @return string
     */
    private function getRoleName($roleId)
    {
        $roles = [
            Role::ROOT => 'ROOT',
            Role::ADMINISTRATOR => 'ADMINISTRATOR',
            Role::HOST => 'HOST',
            Role::RESTARTER => 'RESTARTER',
            Role::GUSET => 'GUEST',
            Role::NETWORK_COORDINATOR => 'NETWORK_COORDINATOR',
        ];

        return $roles[$roleId] ?? 'Unknown Role';
    }

    /**
     * Validate a date string against a format.
     */
    private function validateDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
