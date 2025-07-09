<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FixometerFile;
use App\Services\S3Service;

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
    protected $description = 'Detailed S3 upload test to debug upload issues';

    protected $s3Service;

    public function __construct(S3Service $s3Service)
    {
        parent::__construct();
        $this->s3Service = $s3Service;
    }

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
            $disk = Storage::disk(); // Use default disk
            $testContent = 'Direct upload test at ' . now();
            $testFile = 'uploads/direct-test-' . time() . '.txt';
            
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
                    
                    // Get URL using S3Service
                    $filename = basename($testFile);
                    $url = $this->s3Service->getFileUrl($filename);
                    $this->info("   Generated URL: {$url}");
                    
                    // Test actual HTTP request
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
                    
                    // Clean up
                    $disk->delete($testFile);
                }
            }
        } catch (\Exception $e) {
            $this->error("   Direct upload failed: " . $e->getMessage());
        }

        $this->newLine();

        // Test 2: Check what's in bucket via storage
        $this->info('2️⃣ Checking storage contents...');
        try {
            $disk = Storage::disk(); // Use default disk
            $files = $disk->files('uploads');
            $this->info("   Found " . count($files) . " files in uploads directory");
            
            if (count($files) > 0) {
                $this->info("   Recent files:");
                $recentFiles = array_slice($files, -5); // Show last 5 files
                foreach ($recentFiles as $file) {
                    $this->info("     - " . $file);
                }
            }
        } catch (\Exception $e) {
            $this->error("   Storage listing failed: " . $e->getMessage());
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
                // Check if file exists in storage
                $disk = Storage::disk(); // Use default disk
                $exists = $disk->exists('uploads/' . $filename);
                $this->info("   File exists in storage: " . ($exists ? 'YES' : 'NO'));
                
                if ($exists) {
                    $url = FixometerFile::getUploadFileUrl($filename);
                    $this->info("   Generated URL via helper: {$url}");
                    
                    // Clean up test file
                    $disk->delete('uploads/' . $filename);
                    $this->info("   Test file cleaned up");
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

        // Test 4: Check configuration
        $this->info('4️⃣ Configuration check...');
        $this->info("   Default filesystem disk: " . config('filesystems.default'));
        $this->info("   Using S3: " . ($this->s3Service->isUsingS3() ? 'YES' : 'NO'));
        
        if ($this->s3Service->isUsingS3()) {
            $this->info("   Current S3 disk config:");
            $s3Config = config('filesystems.disks.s3');
            foreach ($s3Config as $key => $value) {
                if (in_array($key, ['key', 'secret']) && $value) {
                    $value = '***' . substr($value, -4);
                }
                $this->info("   {$key}: " . ($value ?? 'NULL'));
            }
        } else {
            $this->info("   Using local storage");
        }

        $this->newLine();
        $this->info('💡 Troubleshooting tips:');
        if ($this->s3Service->isUsingS3()) {
            $this->info('   1. Ensure AWS credentials are correct');
            $this->info('   2. Verify bucket exists and is accessible');
            $this->info('   3. Check IAM permissions for S3 operations');
        } else {
            $this->info('   1. Check if uploads directory exists and is writable');
            $this->info('   2. Verify file permissions');
            $this->info('   3. To use S3: set FILESYSTEM_DISK=s3 in your .env');
        }

        return 0;
    }
} 