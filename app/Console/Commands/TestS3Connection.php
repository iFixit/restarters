<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FixometerFile;
use App\Services\S3Service;

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
        $this->info('🚀 Testing S3 Connection and Upload Functionality');
        $this->newLine();

        // Check current configuration
        $defaultDisk = config('filesystems.default');
        $this->info("Default filesystem disk: {$defaultDisk}");
        $this->info("Using S3: " . ($this->s3Service->isUsingS3() ? 'Yes' : 'No'));

        if (!$this->s3Service->isUsingS3()) {
            $this->warn('⚠️  FILESYSTEM_DISK is not set to "s3". Current setting: ' . $defaultDisk);
            $this->info('To test S3, make sure your .env file has: FILESYSTEM_DISK=s3');
            $this->newLine();
        }

        // Print current S3 configuration first
        $this->printS3Configuration();
        $this->newLine();

        // Test 1: Basic S3 connection
        $this->info('🔍 Test 1: Basic S3 connection...');
        try {
            // Use default disk which should be 's3' if configured
            $disk = Storage::disk();
            
            // Try to list bucket contents (this tests basic connectivity)
            $this->info('   Attempting to connect to default disk...');
            
            if ($this->s3Service->isUsingS3()) {
                $files = $disk->files('uploads', false); // Check uploads directory
                $this->info('✅ Successfully connected to S3 bucket');
                $this->info("   Found " . count($files) . " files in uploads directory");
            } else {
                $files = $disk->files('uploads', false);
                $this->info('✅ Successfully connected to local storage');
                $this->info("   Found " . count($files) . " files in uploads directory");
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to connect to storage: ' . $e->getMessage());
            $this->newLine();
            $this->info('💡 Debugging tips:');
            if ($this->s3Service->isUsingS3()) {
                $this->info('   1. Check if your AWS credentials are correct');
                $this->info('   2. Verify your bucket name and region');
                $this->info('   3. Ensure your AWS user has ListBucket permission');
            } else {
                $this->info('   1. Check if uploads directory exists and is writable');
                $this->info('   2. Verify file permissions');
            }
            return 1;
        }

        // Test 2: Test file upload using S3Service
        $this->info('🔍 Test 2: Testing file upload...');
        try {
            $testContent = 'This is a test file created at ' . now()->toDateTimeString();
            $testFileName = 'test-connection-' . time() . '.txt';
            
            $disk = Storage::disk(); // Use default disk
            
            if ($this->s3Service->isUsingS3()) {
                $this->info("   Uploading test file to uploads/{$testFileName}");
                $success = $disk->put('uploads/' . $testFileName, $testContent);
            } else {
                $this->info("   Uploading test file to uploads/{$testFileName}");
                $success = $disk->put('uploads/' . $testFileName, $testContent);
            }
            
            if ($success) {
                $this->info('✅ Successfully uploaded test file');
                
                // Test file reading
                $filePath = 'uploads/' . $testFileName;
                $retrievedContent = $disk->get($filePath);
                if ($retrievedContent === $testContent) {
                    $this->info('✅ Successfully read back test file content');
                } else {
                    $this->error('❌ File content mismatch after upload');
                }
                
                // Get URL using the S3Service
                $url = $this->s3Service->getFileUrl($testFileName);
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
                $disk->delete($filePath);
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
            
            $disk = Storage::disk(); // Use default disk
            
            // Test original image
            $disk->put('uploads/' . $testImageName, $imageContent);
            $this->info('✅ Uploaded test image: ' . $testImageName);
            
            // Test thumbnail
            $disk->put('uploads/thumbnail_' . $testImageName, $imageContent);
            $this->info('✅ Uploaded test thumbnail: thumbnail_' . $testImageName);
            
            // Test mid-size
            $disk->put('uploads/mid_' . $testImageName, $imageContent);
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
            $disk->delete([
                'uploads/' . $testImageName, 
                'uploads/thumbnail_' . $testImageName, 
                'uploads/mid_' . $testImageName
            ]);
            $this->info('✅ Test images cleaned up');
            
        } catch (\Exception $e) {
            $this->error('❌ Image upload test failed: ' . $e->getMessage());
            return 1;
        }

        // Test 4: Test permissions
        $this->info('🔍 Test 4: Testing storage permissions...');
        try {
            $disk = Storage::disk(); // Use default disk
            
            // Test listing
            $files = $disk->files('uploads');
            $this->info('✅ Can list storage contents (' . count($files) . ' files)');
            
            // Test if we can check file existence
            $testFileName = 'uploads/permission-test-' . time() . '.txt';
            $disk->put($testFileName, 'test');
            
            if ($disk->exists($testFileName)) {
                $this->info('✅ Can check file existence');
            }
            
            // Test deletion
            $disk->delete($testFileName);
            $this->info('✅ Can delete files');
            
        } catch (\Exception $e) {
            $this->error('❌ Permission test failed: ' . $e->getMessage());
            if ($this->s3Service->isUsingS3()) {
                $this->warn('💡 Check your IAM permissions for the AWS user');
            } else {
                $this->warn('💡 Check your local file permissions');
            }
        }

        $this->newLine();
        $this->info('🎉 Storage testing completed!');
        
        return 0;
    }

    private function printS3Configuration()
    {
        $this->info('📋 Current Storage Configuration:');
        $configData = [
            ['FILESYSTEM_DISK', config('filesystems.default', 'Not set')],
        ];

        if ($this->s3Service->isUsingS3()) {
            $configData = array_merge($configData, [
                ['AWS_ACCESS_KEY_ID', config('filesystems.disks.s3.key') ? '***' . substr(config('filesystems.disks.s3.key'), -4) : 'Not set'],
                ['AWS_SECRET_ACCESS_KEY', config('filesystems.disks.s3.secret') ? 'Set (***' . substr(config('filesystems.disks.s3.secret'), -4) . ')' : 'Not set'],
                ['AWS_DEFAULT_REGION', config('filesystems.disks.s3.region', 'Not set')],
                ['AWS_BUCKET', config('filesystems.disks.s3.bucket', 'Not set')],
                ['AWS_URL', config('filesystems.disks.s3.url', 'Not set')],
                ['AWS_ENDPOINT', config('filesystems.disks.s3.endpoint', 'Not set')],
            ]);
        } else {
            $configData[] = ['Storage Type', 'Local filesystem'];
        }

        $this->table(['Setting', 'Value'], $configData);
    }
} 