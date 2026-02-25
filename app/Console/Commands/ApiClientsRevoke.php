<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use Illuminate\Console\Command;

class ApiClientsRevoke extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-clients:revoke {id : API client ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoke an integration API client';

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

        $client->active = false;
        $client->save();

        $this->info("Revoked API client {$client->id} ({$client->name}).");

        return 0;
    }
}
