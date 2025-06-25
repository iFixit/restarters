<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FixometerFile;

class TestS3Upload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uploads:test-s3-detailed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detailed S3 upload test to debug LocalStack issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Detailed S3 Upload Debug Test');
        $this->newLine();

        // Test 1: Direct Storage upload
        $this->info('1️⃣ Testing direct Storage upload...');
        try {
            $disk = Storage::disk('s3_uploads');
            $testContent = 'Direct upload test at ' . now();
            $testFile = 'direct-test-' . time() . '.txt';
            
            $this->info("   Uploading: {$testFile}");
            $result = $disk->put($testFile, $testContent);
            $this->info("   Upload result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            if ($result) {
                // Check if file exists
                $exists = $disk->exists($testFile);
                $this->info("   File exists check: " . ($exists ? 'YES' : 'NO'));
                
                if ($exists) {
                    $content = $disk->get($testFile);
                    $this->info("   Retrieved content: " . substr($content, 0, 50) . '...');
                    
                    // Get URL
                    $url = $disk->url($testFile);
                    $this->info("   Generated URL: {$url}");
                    
                    // Test actual HTTP request to LocalStack
                    $this->info("   Testing HTTP GET to URL...");
                    try {
                        $response = \Http::timeout(10)->get($url);
                        $this->info("   HTTP Status: " . $response->status());
                        if ($response->successful()) {
                            $this->info("   Response body: " . substr($response->body(), 0, 100));
                        }
                    } catch (\Exception $e) {
                        $this->error("   HTTP request failed: " . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error("   Direct upload failed: " . $e->getMessage());
        }

        $this->newLine();

        // Test 2: Check what's in bucket via AWS CLI
        $this->info('2️⃣ Checking bucket contents via AWS CLI...');
        try {
            $output = shell_exec('aws --endpoint-url=https://localhost.localstack.cloud:4566 s3 ls s3://restarters/test/ --recursive 2>&1');
            $this->info("   Bucket contents:");
            $this->info($output ?: '   (empty)');
        } catch (\Exception $e) {
            $this->error("   AWS CLI check failed: " . $e->getMessage());
        }

        $this->newLine();

        // Test 3: Test FixometerFile upload with detailed logging
        $this->info('3️⃣ Testing FixometerFile upload with debugging...');
        
        // Create a temporary test file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        file_put_contents($tempFile, 'Test image content for FixometerFile');
        
        // Simulate $_FILES array
        $_FILES['test_file'] = [
            'name' => 'test-image.txt',
            'type' => 'text/plain',
            'size' => filesize($tempFile),
            'tmp_name' => $tempFile,
            'error' => UPLOAD_ERR_OK,
        ];

        try {
            $this->info("   Creating FixometerFile instance...");
            $fixometerFile = new FixometerFile();
            
            // Enable testing mode to avoid move_uploaded_file issues
            FixometerFile::$uploadTesting = true;
            
            $this->info("   Calling upload method...");
            $filename = $fixometerFile->upload('test_file', 'image');
            
            $this->info("   Upload returned filename: " . ($filename ?: 'NULL'));
            
            if ($filename) {
                // Check if file exists in S3
                $disk = Storage::disk('s3_uploads');
                $exists = $disk->exists($filename);
                $this->info("   File exists in S3: " . ($exists ? 'YES' : 'NO'));
                
                if ($exists) {
                    $url = FixometerFile::getUploadFileUrl($filename);
                    $this->info("   Generated URL via helper: {$url}");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("   FixometerFile upload failed: " . $e->getMessage());
            $this->error("   Stack trace: " . $e->getTraceAsString());
        }

        // Clean up
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        
        FixometerFile::$uploadTesting = false;

        $this->newLine();

        // Test 4: Check LocalStack logs
        $this->info('4️⃣ LocalStack configuration check...');
        $this->info("   Current S3 disk config:");
        $s3Config = config('filesystems.disks.s3_uploads');
        foreach ($s3Config as $key => $value) {
            if (in_array($key, ['key', 'secret']) && $value) {
                $value = '***HIDDEN***';
            }
            $this->info("   {$key}: " . ($value ?? 'NULL'));
        }

        $this->newLine();
        $this->info('💡 Troubleshooting tips:');
        $this->info('   1. Check LocalStack logs: docker logs <localstack-container>');
        $this->info('   2. Verify bucket policy allows uploads');
        $this->info('   3. Check if LocalStack S3 service is properly configured');
        $this->info('   4. Try recreating the bucket with proper permissions');

        return 0;
    }
} 