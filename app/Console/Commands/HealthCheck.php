<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class HealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check application health status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $checks = [
            'Database' => $this->checkDatabase(),
            'Storage' => $this->checkStorage(),
        ];

        $allPassed = true;
        foreach ($checks as $name => $passed) {
            if ($passed) {
                $this->info("✓ {$name}: OK");
            } else {
                $this->error("✗ {$name}: FAILED");
                $allPassed = false;
            }
        }

        if ($allPassed) {
            $this->info('All health checks passed');
            return 0;
        } else {
            $this->error('Some health checks failed');
            return 1;
        }
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        try {
            return is_writable(storage_path());
        } catch (Exception $e) {
            return false;
        }
    }
} 