<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ApiClientsCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-clients:create
                            {--name= : Display name for the integration client}
                            {--scopes=events:read : Comma-separated scopes}
                            {--origins= : Comma-separated allowed origins}
                            {--networks= : Comma-separated allowed network IDs}
                            {--rate=120 : Requests per minute}
                            {--expires-at= : Expiration datetime}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a read-only integration API client and print its secret once';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = trim((string) $this->option('name'));

        if ($name === '') {
            $this->error('The --name option is required.');
            return 1;
        }

        $rate = (int) $this->option('rate');
        if ($rate < 1) {
            $this->error('The --rate option must be greater than zero.');
            return 1;
        }

        $scopes = $this->parseCsvOption((string) $this->option('scopes'));
        $origins = $this->parseCsvOption((string) $this->option('origins'));
        $networks = $this->parseCsvOption((string) $this->option('networks'));
        $networkIds = array_values(array_filter(array_map('intval', $networks), fn (int $id) => $id > 0));

        $expiresAt = null;
        if ($this->option('expires-at')) {
            $expiresAt = Carbon::parse((string) $this->option('expires-at'));
        }

        $plainToken = Str::random(64);

        $client = ApiClient::create([
            'name' => $name,
            'token_hash' => hash('sha256', $plainToken),
            'scopes' => $scopes ?: ['events:read'],
            'allowed_origins' => $origins ?: null,
            'allowed_network_ids' => $networkIds ?: null,
            'rate_limit_per_minute' => $rate,
            'active' => true,
            'expires_at' => $expiresAt,
        ]);

        $this->info('API client created.');
        $this->line("ID: {$client->id}");
        $this->line("Name: {$client->name}");
        $this->line("Token: {$plainToken}");
        $this->warn('Store this token now. It will not be shown again.');

        return 0;
    }

    private function parseCsvOption(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
