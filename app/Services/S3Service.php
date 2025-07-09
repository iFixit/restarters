<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class S3Service
{
    protected $disk;
    protected $imageManager;

    public function __construct()
    {
        $this->disk = Storage::disk(); // Use default disk
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Check if we're using S3 for uploads
     */
    public function isUsingS3(): bool
    {
        return config('filesystems.default') === 's3';
    }

    /**
     * Upload a file to the configured storage
     */
    public function uploadFile(UploadedFile $file, string $directory = 'uploads', array $options = []): string
    {
        $filename = $this->generateFileName($file);
        $path = $directory . '/' . $filename;

        if ($this->isUsingS3()) {
            return $this->uploadToS3($file, $path, $options);
        }

        return $this->uploadToLocal($file, $path);
    }

    /**
     * Upload image with resizing options
     */
    public function uploadImage(UploadedFile $file, string $directory = 'uploads', array $sizes = []): array
    {
        $filename = $this->generateFileName($file);
        $results = [];

        // Upload original image
        $originalPath = $directory . '/' . $filename;
        $results['original'] = $this->uploadFile($file, $directory);

        // Create resized versions if requested
        if (!empty($sizes)) {
            foreach ($sizes as $size => $dimensions) {
                $resizedPath = $directory . '/' . $size . '_' . $filename;
                $results[$size] = $this->uploadResizedImage($file, $resizedPath, $dimensions);
            }
        }

        return $results;
    }

    /**
     * Upload a resized image
     */
    protected function uploadResizedImage(UploadedFile $file, string $path, array $dimensions): string
    {
        $image = $this->imageManager->read($file->getRealPath());
        
        if (isset($dimensions['width']) && isset($dimensions['height'])) {
            $image->scaleDown($dimensions['width'], $dimensions['height']);
        } elseif (isset($dimensions['width'])) {
            $image->scaleDown($dimensions['width']);
        } elseif (isset($dimensions['height'])) {
            $image->scaleDown(height: $dimensions['height']);
        }

        $imageContent = $image->toJpeg(90);
        
        if ($this->isUsingS3()) {
            $this->disk->put($path, $imageContent, 'private');
        } else {
            // For local storage, we need to use the correct path
            $this->disk->put($path, $imageContent);
        }

        return $path;
    }

    /**
     * Upload file to S3
     */
    protected function uploadToS3(UploadedFile $file, string $path, array $options = []): string
    {
        $visibility = $options['visibility'] ?? 'private';
        $this->disk->put($path, file_get_contents($file->getRealPath()), $visibility);
        return $path;
    }

    /**
     * Upload file to local storage
     */
    protected function uploadToLocal(UploadedFile $file, string $path): string
    {
        $this->disk->put($path, file_get_contents($file->getRealPath()));
        return $path;
    }

    /**
     * Generate a unique filename
     */
    protected function generateFileName(UploadedFile $file): string
    {
        return time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
    }

    /**
     * Get file URL
     */
    public function getFileUrl(string $filename, string $type = 'original'): ?string
    {
        if (empty($filename)) {
            return null;
        }

        $path = $this->buildFilePath($filename, $type);

        if ($this->isUsingS3()) {
            return $this->getS3FileUrl($path);
        }

        return $this->getLocalFileUrl($path);
    }

    /**
     * Get S3 file URL (uses AWS_URL if configured for CloudFront)
     */
    protected function getS3FileUrl(string $path): string
    {
        // Use AWS_URL for CloudFront distribution if configured
        $awsUrl = config('filesystems.disks.s3.url');
        if ($awsUrl) {
            return rtrim($awsUrl, '/') . '/' . ltrim($path, '/');
        }

        // Fallback to direct S3 URL
        return $this->disk->url($path);
    }

    /**
     * Get local file URL
     */
    protected function getLocalFileUrl(string $path): string
    {
        return $this->disk->url($path);
    }

    /**
     * Build file path with type prefix
     */
    protected function buildFilePath(string $filename, string $type): string
    {
        $prefix = '';
        if ($type === 'thumbnail') {
            $prefix = 'thumbnail_';
        } elseif ($type === 'mid') {
            $prefix = 'mid_';
        }

        return 'uploads/' . $prefix . $filename;
    }

    /**
     * Delete file from storage
     */
    public function deleteFile(string $path): bool
    {
        return $this->disk->delete($path);
    }

    /**
     * Check if file exists
     */
    public function fileExists(string $path): bool
    {
        return $this->disk->exists($path);
    }

    /**
     * Get file size
     */
    public function getFileSize(string $path): int
    {
        return $this->disk->size($path);
    }

    /**
     * Get file content
     */
    public function getFileContent(string $path): string
    {
        return $this->disk->get($path);
    }

    /**
     * Get temporary URL for private files
     */
    public function getTemporaryUrl(string $path, int $expiresInMinutes = 60): string
    {
        if ($this->isUsingS3()) {
            return $this->disk->temporaryUrl($path, now()->addMinutes($expiresInMinutes));
        }

        // For local files, return the regular URL
        return $this->disk->url($path);
    }
} 