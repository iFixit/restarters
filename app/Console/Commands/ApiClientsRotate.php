<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ApiClientsRotate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-clients:rotate {id : API client ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rotate an integration API client token';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $client = ApiClient::find($this->argument('id'));

        if (!$client) {
            $this->error('API client not found.');
            return 1;
        }

        $plainToken = Str::random(64);

        $client->token_hash = hash('sha256', $plainToken);
        $client->active = true;
        $client->save();

        $this->info("Rotated API client {$client->id} ({$client->name}).");
        $this->line("Token: {$plainToken}");
        $this->warn('Store this token now. It will not be shown again.');

        return 0;
    }
}
