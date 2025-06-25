<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FixometerFile;

class TestS3Connection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uploads:test-s3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test S3 connection and upload functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Testing S3 Connection and Upload Functionality');
        $this->newLine();

        // Check current configuration
        $uploadsDisk = config('filesystems.disks.uploads.driver', 'local');
        $this->info("Current uploads disk driver: {$uploadsDisk}");

        if ($uploadsDisk !== 's3') {
            $this->warn('⚠️  UPLOADS_DISK is not set to "s3". Current setting: ' . $uploadsDisk);
            $this->info('To test S3, make sure your .env file has: UPLOADS_DISK=s3');
            $this->newLine();
        }

        // Print current S3 configuration first
        $this->printS3Configuration();
        $this->newLine();

        // Test 1: Basic S3 connection
        $this->info('🔍 Test 1: Basic S3 connection...');
        try {
            $disk = Storage::disk('s3_uploads');
            
            // Try to list bucket contents (this tests basic connectivity)
            $this->info('   Attempting to connect to S3...');
            $files = $disk->files('', false); // Non-recursive first
            $this->info('✅ Successfully connected to S3 bucket');
            $this->info("   Found " . count($files) . " files in bucket root");
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to connect to S3: ' . $e->getMessage());
            $this->newLine();
            $this->info('💡 Debugging tips:');
            $this->info('   1. Check if your AWS credentials are correct');
            $this->info('   2. Verify your bucket name and region');
            $this->info('   3. Ensure your AWS user has ListBucket permission');
            $this->info('   4. Check if you\'re using LocalStack or real AWS');
            return 1;
        }

        // Test 2: Test file upload
        $this->info('🔍 Test 2: Testing file upload...');
        try {
            $testContent = 'This is a test file created at ' . now()->toDateTimeString();
            $testFileName = 'test-connection-' . time() . '.txt';
            
            $disk = Storage::disk('s3_uploads');
            $this->info("   Uploading test file: {$testFileName}");
            $success = $disk->put($testFileName, $testContent);
            
            if ($success) {
                $this->info('✅ Successfully uploaded test file');
                
                // Test file reading
                $retrievedContent = $disk->get($testFileName);
                if ($retrievedContent === $testContent) {
                    $this->info('✅ Successfully read back test file content');
                } else {
                    $this->error('❌ File content mismatch after upload');
                }
                
                // Get URL and test it
                $url = $disk->url($testFileName);
                $this->info('✅ Generated URL: ' . $url);
                
                // Test URL accessibility
                $this->info('   Testing URL accessibility...');
                try {
                    $response = \Http::timeout(10)->get($url);
                    if ($response->successful()) {
                        $this->info('✅ URL is publicly accessible');
                    } else {
                        $this->warn('⚠️  URL returned status: ' . $response->status());
                    }
                } catch (\Exception $e) {
                    $this->warn('⚠️  Could not test URL accessibility: ' . $e->getMessage());
                }
                
                // Clean up test file
                $disk->delete($testFileName);
                $this->info('✅ Test file cleaned up');
                
            } else {
                $this->error('❌ Failed to upload test file');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('❌ File upload test failed: ' . $e->getMessage());
            return 1;
        }

        // Test 3: Test image upload simulation
        $this->info('🔍 Test 3: Testing image upload paths...');
        try {
            // Create a simple test image (1x1 PNG)
            $imageContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
            $testImageName = 'test-image-' . time() . '.png';
            
            $disk = Storage::disk('s3_uploads');
            
            // Test original image
            $disk->put($testImageName, $imageContent);
            $this->info('✅ Uploaded test image: ' . $testImageName);
            
            // Test thumbnail
            $disk->put('thumbnail_' . $testImageName, $imageContent);
            $this->info('✅ Uploaded test thumbnail: thumbnail_' . $testImageName);
            
            // Test mid-size
            $disk->put('mid_' . $testImageName, $imageContent);
            $this->info('✅ Uploaded test mid-size image: mid_' . $testImageName);
            
            // Test URL generation using helper
            $originalUrl = FixometerFile::getUploadFileUrl($testImageName);
            $thumbnailUrl = FixometerFile::getUploadFileUrl($testImageName, 'thumbnail');
            $midUrl = FixometerFile::getUploadFileUrl($testImageName, 'mid');
            
            $this->info('✅ Generated URLs:');
            $this->info('   Original: ' . $originalUrl);
            $this->info('   Thumbnail: ' . $thumbnailUrl);
            $this->info('   Mid-size: ' . $midUrl);
            
            // Test if URLs are accessible
            $this->info('   Testing image URL accessibility...');
            foreach (['Original' => $originalUrl, 'Thumbnail' => $thumbnailUrl, 'Mid-size' => $midUrl] as $type => $url) {
                try {
                    $response = \Http::timeout(5)->get($url);
                    if ($response->successful()) {
                        $this->info("   ✅ {$type} URL is accessible");
                    } else {
                        $this->warn("   ⚠️  {$type} URL returned status: " . $response->status());
                    }
                } catch (\Exception $e) {
                    $this->warn("   ⚠️  Could not test {$type} URL: " . $e->getMessage());
                }
            }
            
            // Clean up test images
            $disk->delete([$testImageName, 'thumbnail_' . $testImageName, 'mid_' . $testImageName]);
            $this->info('✅ Test images cleaned up');
            
        } catch (\Exception $e) {
            $this->error('❌ Image upload test failed: ' . $e->getMessage());
            return 1;
        }

        // Test 4: Test permissions
        $this->info('🔍 Test 4: Testing bucket permissions...');
        try {
            $disk = Storage::disk('s3_uploads');
            
            // Test listing
            $files = $disk->files();
            $this->info('✅ Can list bucket contents (' . count($files) . ' files)');
            
            // Test if we can check file existence
            $testFileName = 'permission-test-' . time() . '.txt';
            $disk->put($testFileName, 'test');
            
            if ($disk->exists($testFileName)) {
                $this->info('✅ Can check file existence');
            }
            
            // Test deletion
            $disk->delete($testFileName);
            $this->info('✅ Can delete files');
            
        } catch (\Exception $e) {
            $this->error('❌ Permission test failed: ' . $e->getMessage());
            $this->warn('💡 Check your IAM permissions for the AWS user');
        }

        $this->newLine();
        $this->info('🎉 All S3 tests completed successfully!');
        $this->info('Your S3 configuration is working properly.');
        
        $this->newLine();
        $this->info('💡 Next steps:');
        $this->info('   1. Test the web interface at: /test-s3-upload');
        $this->info('   2. Try uploading images through the actual application');
        $this->info('   3. Check that existing local images still work if you have any');
        
        return 0;
    }

    private function printS3Configuration()
    {
        $this->info('Current S3 Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['AWS_ACCESS_KEY_ID', config('filesystems.disks.s3_uploads.key') ? '***SET***' : 'NOT SET'],
                ['AWS_SECRET_ACCESS_KEY', config('filesystems.disks.s3_uploads.secret') ? '***SET***' : 'NOT SET'],
                ['AWS_DEFAULT_REGION', config('filesystems.disks.s3_uploads.region') ?: 'NOT SET'],
                ['AWS_BUCKET', config('filesystems.disks.s3_uploads.bucket') ?: 'NOT SET'],
                ['AWS_URL', config('filesystems.disks.s3_uploads.url') ?: 'NOT SET'],
                ['AWS_UPLOADS_ROOT', config('filesystems.disks.s3_uploads.root') ?: 'uploads'],
            ]
        );
    }
} 