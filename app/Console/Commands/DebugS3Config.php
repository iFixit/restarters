<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FixometerFile;
use App\Services\S3Service;

class DebugS3Config extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uploads:debug-s3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug S3 configuration and URL generation';

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
        $this->info('🔍 S3 Configuration Debug Tool');
        $this->newLine();

        // 1. Check environment variables
        $this->info('1️⃣ Environment Variables:');
        $envVars = [
            'FILESYSTEM_DISK' => env('FILESYSTEM_DISK', 'NOT SET'),
            'AWS_ACCESS_KEY_ID' => env('AWS_ACCESS_KEY_ID') ? '***SET***' : 'NOT SET',
            'AWS_SECRET_ACCESS_KEY' => env('AWS_SECRET_ACCESS_KEY') ? '***SET***' : 'NOT SET',
            'AWS_DEFAULT_REGION' => env('AWS_DEFAULT_REGION', 'NOT SET'),
            'AWS_BUCKET' => env('AWS_BUCKET', 'NOT SET'),
            'AWS_URL' => env('AWS_URL', 'NOT SET'),
            'AWS_ENDPOINT' => env('AWS_ENDPOINT', 'NOT SET'),
            'AWS_USE_PATH_STYLE_ENDPOINT' => env('AWS_USE_PATH_STYLE_ENDPOINT', 'NOT SET'),
        ];

        foreach ($envVars as $key => $value) {
            $this->info("   {$key}: {$value}");
        }
        $this->newLine();

        // 2. Check config values
        $this->info('2️⃣ Resolved Configuration:');
        $this->info("   Default filesystem disk: " . config('filesystems.default'));
        $this->info("   Using S3: " . ($this->s3Service->isUsingS3() ? 'YES' : 'NO'));
        
        if ($this->s3Service->isUsingS3()) {
            $s3Config = config('filesystems.disks.s3');
            foreach ($s3Config as $key => $value) {
                if (in_array($key, ['key', 'secret']) && $value) {
                    $value = '***HIDDEN***';
                }
                $this->info("   {$key}: " . ($value ?: 'NULL'));
            }
        } else {
            $this->info("   Using local storage: " . config('filesystems.default'));
        }
        $this->newLine();

        // 3. Test URL generation
        $this->info('3️⃣ URL Generation Test:');
        $testFilename = 'test-file.jpg';
        
        try {
            $originalUrl = FixometerFile::getUploadFileUrl($testFilename);
            $thumbnailUrl = FixometerFile::getUploadFileUrl($testFilename, 'thumbnail');
            $midUrl = FixometerFile::getUploadFileUrl($testFilename, 'mid');

            $this->info("   Original URL: {$originalUrl}");
            $this->info("   Thumbnail URL: {$thumbnailUrl}");
            $this->info("   Mid-size URL: {$midUrl}");
        } catch (\Exception $e) {
            $this->error("   ❌ URL generation failed: " . $e->getMessage());
        }
        $this->newLine();

        // 4. Test direct Storage calls
        $this->info('4️⃣ Direct Storage URL Test:');
        try {
            $disk = Storage::disk(); // Use default disk
            $directUrl = $disk->url('uploads/' . $testFilename);
            $this->info("   Storage::disk()->url(): {$directUrl}");
        } catch (\Exception $e) {
            $this->error("   ❌ Direct storage URL failed: " . $e->getMessage());
        }
        $this->newLine();

        // 5. Check for common issues
        $this->info('5️⃣ Common Issues Check:');
        
        if ($this->s3Service->isUsingS3()) {
            // Check if AWS_URL ends with bucket name
            $awsUrl = env('AWS_URL');
            $bucket = env('AWS_BUCKET');
            if ($awsUrl && $bucket) {
                if (str_ends_with($awsUrl, $bucket)) {
                    $this->info('   ✅ AWS_URL correctly ends with bucket name');
                } else {
                    $this->warn("   ⚠️  AWS_URL should end with bucket name. Expected: https://{$bucket}.s3.amazonaws.com");
                }
            }

            // Check region format
            $region = env('AWS_DEFAULT_REGION');
            if ($region && !preg_match('/^[a-z0-9-]+$/', $region)) {
                $this->warn('   ⚠️  AWS_DEFAULT_REGION format looks suspicious: ' . $region);
            } else {
                $this->info('   ✅ AWS_DEFAULT_REGION format looks correct');
            }
        } else {
            $this->info('   ✅ Using local storage - no S3 configuration needed');
        }

        $this->newLine();

        // 6. Provide specific help for the XML error
        if ($this->s3Service->isUsingS3()) {
            $this->info('6️⃣ XML Error Troubleshooting:');
            $this->info('   The XML error you\'re seeing typically means:');
            $this->info('   • You\'re accessing a raw S3 URL directly in the browser');
            $this->info('   • The URL format is incorrect');
            $this->info('   • The bucket has a CORS issue');
            $this->info('   • You\'re missing the correct AWS_URL configuration');
            $this->newLine();
        }

        $this->info('💡 Recommendations:');
        if (!$this->s3Service->isUsingS3()) {
            $this->warn('   1. Set FILESYSTEM_DISK=s3 in your .env file to use S3');
        }
        
        if ($this->s3Service->isUsingS3() && !env('AWS_URL')) {
            $this->warn('   2. Set AWS_URL in your .env file (e.g., https://d1234567890.cloudfront.net for CloudFront)');
        }

        $this->info('   3. Don\'t access S3 URLs directly - use them through your application');
        $this->info('   4. Test with: php artisan uploads:test-s3');
        $this->info('   5. Use the web interface: /test-s3-upload');

        return 0;
    }
} 