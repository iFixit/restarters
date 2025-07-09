<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FixometerFile;
use App\Services\S3Service;

class TestImageUpload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uploads:test-image-upload {--image=} {--cleanup=true}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test image upload and thumbnail creation with S3';

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
        $this->info('🧪 Testing Image Upload and Thumbnail Creation');
        $this->newLine();

        // Check if S3 is configured
        if (!$this->s3Service->isUsingS3()) {
            $this->warn('⚠️  S3 is not configured. Using local storage instead.');
        } else {
            $this->info('✅ S3 is configured and active');
        }

        $imagePath = $this->option('image');
        $cleanup = $this->option('cleanup') !== 'false';

        if (!$imagePath) {
            // Create a simple test image if none provided
            $this->info('📸 Creating test image...');
            $imagePath = $this->createTestImage();
        }

        if (!file_exists($imagePath)) {
            $this->error('❌ Image file not found: ' . $imagePath);
            return 1;
        }

        $this->info('📂 Using image: ' . $imagePath);

        try {
            // Test the upload
            $this->info('⬆️  Testing image upload...');
            
            // Simulate $_FILES array
            $_FILES['test_image'] = [
                'name' => basename($imagePath),
                'type' => mime_content_type($imagePath),
                'size' => filesize($imagePath),
                'tmp_name' => $imagePath,
                'error' => UPLOAD_ERR_OK,
            ];

            // Enable testing mode
            FixometerFile::$uploadTesting = true;

            $fixometerFile = new FixometerFile();
            $result = $fixometerFile->upload('test_image', 'image', 1, 1);

            if ($result) {
                $this->info('✅ Image upload successful!');
                $this->info('📁 Result: ' . $result);

                // Get the filename from the result
                $filename = basename($result);
                
                // Test URL generation
                $this->info('🔗 Testing URL generation...');
                $originalUrl = FixometerFile::getUploadFileUrl($filename);
                $thumbnailUrl = FixometerFile::getUploadFileUrl($filename, 'thumbnail');
                $midUrl = FixometerFile::getUploadFileUrl($filename, 'mid');

                $this->info('   Original: ' . $originalUrl);
                $this->info('   Thumbnail: ' . $thumbnailUrl);
                $this->info('   Mid-size: ' . $midUrl);

                // Test file existence
                $this->info('📋 Testing file existence...');
                $disk = Storage::disk();
                
                $originalExists = $disk->exists('uploads/' . $filename);
                $thumbnailExists = $disk->exists('uploads/thumbnail_' . $filename);
                $midExists = $disk->exists('uploads/mid_' . $filename);

                $this->info('   Original exists: ' . ($originalExists ? '✅' : '❌'));
                $this->info('   Thumbnail exists: ' . ($thumbnailExists ? '✅' : '❌'));
                $this->info('   Mid-size exists: ' . ($midExists ? '✅' : '❌'));

                // Test HTTP accessibility if using S3
                if ($this->s3Service->isUsingS3()) {
                    $this->info('🌐 Testing HTTP accessibility...');
                    $urls = [
                        'Original' => $originalUrl,
                        'Thumbnail' => $thumbnailUrl,
                        'Mid-size' => $midUrl,
                    ];

                    foreach ($urls as $type => $url) {
                        try {
                            $response = \Http::timeout(10)->get($url);
                            $status = $response->successful() ? '✅' : '❌ (Status: ' . $response->status() . ')';
                            $this->info("   {$type}: {$status}");
                        } catch (\Exception $e) {
                            $this->warn("   {$type}: ❌ (Error: " . $e->getMessage() . ')');
                        }
                    }
                }

                // Cleanup if requested
                if ($cleanup) {
                    $this->info('🧹 Cleaning up test files...');
                    $disk->delete([
                        'uploads/' . $filename,
                        'uploads/thumbnail_' . $filename,
                        'uploads/mid_' . $filename,
                    ]);
                    $this->info('✅ Cleanup completed');
                }

            } else {
                $this->error('❌ Image upload failed');
                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        } finally {
            // Cleanup
            FixometerFile::$uploadTesting = false;
            
            // Remove test image if we created it
            if (strpos($imagePath, 'test-image-') !== false && file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $this->info('🎉 Test completed successfully!');
        return 0;
    }

    /**
     * Create a simple test image
     */
    private function createTestImage(): string
    {
        $tempDir = sys_get_temp_dir();
        $imagePath = $tempDir . '/test-image-' . time() . '.png';

        // Create a simple 200x200 colored image
        $image = imagecreate(200, 200);
        $blue = imagecolorallocate($image, 0, 100, 200);
        $white = imagecolorallocate($image, 255, 255, 255);
        
        imagefill($image, 0, 0, $blue);
        imagestring($image, 5, 50, 90, 'TEST', $white);
        
        imagepng($image, $imagePath);
        imagedestroy($image);

        return $imagePath;
    }
} 