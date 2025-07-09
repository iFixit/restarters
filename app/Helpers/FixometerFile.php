<?php

namespace App\Helpers;

use App\Models\Images;
use App\Models\Xref;
use App\Services\S3Service;
use Illuminate\Database\Eloquent\Model;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FixometerFile extends Model
{
    public $path;
    public $file;
    public $ext;

    protected $table;

    protected $dates = true;
    public static $uploadTesting = false;

    protected $s3Service;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * Get S3 Service instance
     */
    protected function getS3Service()
    {
        if (!$this->s3Service) {
            $this->s3Service = app(S3Service::class);
        }
        return $this->s3Service;
    }

    /**
     * Static helper to get upload URL for files
     * This can be used throughout the application
     */
    public static function getUploadFileUrl($filename, $type = 'original')
    {
        if (empty($filename)) {
            return null;
        }

        // If it's already a full URL, return as is
        if (filter_var($filename, FILTER_VALIDATE_URL)) {
            return $filename;
        }

        $s3Service = app(S3Service::class);
        return $s3Service->getFileUrl($filename, $type);
    }

    public function move($from, $to) {
        // This is for phpunit tests.
        if (FixometerFile::$uploadTesting) {
            return copy($from, $to);
        } else {
            return @move_uploaded_file($from, $to);
        }
    }

    public function copy($from, $to) {
        // This is for phpunit tests.
        copy($from, $to);
    }

    /**
     * receives the POST data from an HTML form
     * processes file upload and saves
     * to database, depending on filetype
     * */
    public function upload($file, $type, $reference = null, $referenceType = null, $multiple = false, $profile = false, $ajax = false, $crop = true)
    {
        $clear = true; // purge pre-existing images from db - this is the default behaviour

        if (is_string($file) && isset($_FILES[$file])) {
            $user_file = $_FILES[$file];
        } elseif (is_array($file)) { // multiple file uploads means we do not purge pre-existing images
            $user_file = $file;
            $clear = false;
        }

        if ($multiple) {
            $clear = false;
        }

        if ($clear) {
            Xref::where('reference', $reference)
                  ->where('reference_type', $referenceType)
                    ->forceDelete();
        }

        if ($ajax && gettype($user_file['tmp_name']) == 'array') {
            $error = $user_file['error'][0];
            $tmp_name = $user_file['tmp_name'][0];
        } else {
            $error = $user_file['error'];
            $tmp_name = $user_file['tmp_name'];
        }

        /** if we have no error, proceed to elaborate and upload **/
        if ($error == UPLOAD_ERR_OK) {
            $filename = $this->filename($tmp_name);
            $this->file = $filename;
            
            // Log upload attempt for debugging
            \Log::info('FixometerFile upload attempt', [
                'filename' => $filename,
                'type' => $type,
                'storage_type' => $this->getS3Service()->isUsingS3() ? 's3' : 'local',
                'reference' => $reference,
                'reference_type' => $referenceType
            ]);
            
            if ($this->getS3Service()->isUsingS3()) {
                // Upload to S3
                $fileContents = file_get_contents($tmp_name);
                $uploadResult = Storage::disk('s3')->put('uploads/' . $filename, $fileContents);
                
                if (!$uploadResult) {
                    \Log::error('Failed to upload file to S3', ['filename' => $filename]);
                    return false;
                }
                
                // Give S3 a moment to make the file available for reading
                // This helps prevent timing issues with immediate thumbnail creation
                usleep(500000); // 0.5 second
                
                // Verify file exists before proceeding
                if (!Storage::disk('s3')->exists('uploads/' . $filename)) {
                    \Log::error('File not available in S3 after upload', ['filename' => $filename]);
                    return false;
                }
                
                $this->path = $filename;
            } else {
                // Upload to local storage
                $uploadsPath = public_path('uploads');
                $lpath = $uploadsPath . '/' . $filename;
                if (!$this->move($tmp_name, $lpath)) {
                    return false;
                }
                $this->path = $lpath;
            }
            
            $data = [];
            $data['path'] = $this->file;

            $imageManager = new ImageManager(new Driver());

            // In test mode, we skip image manipulations to avoid dependency issues
            if (!FixometerFile::$uploadTesting) {
                if (!$this->getS3Service()->isUsingS3()) {
                    // Fix orientation for local files
                    $imageManager->read($this->path)->save($this->path);
                }
                
                // Create thumbnails
                $this->createThumbnails($imageManager, $filename);
            }

            if ($type == 'image') {
                // Save to database
                $data['object_type'] = 2;
                $idxref = $this->saveToDatabase($data, $reference, $referenceType);
                
                return $idxref;
            } else {
                return $this->file;
            }
        }

        return false;
    }

    /**
     * Create thumbnail images
     */
    protected function createThumbnails($imageManager, $filename)
    {
        if ($this->getS3Service()->isUsingS3()) {
            // For S3, create thumbnails and upload them
            $thumbnailSuccess = $this->createS3Thumbnail($imageManager, $filename, 'thumbnail_', 150, 150);
            $midSuccess = $this->createS3Thumbnail($imageManager, $filename, 'mid_', 300, 300);
            
            // Log overall thumbnail creation result
            \Log::info('S3 thumbnail creation completed', [
                'filename' => $filename,
                'thumbnail_success' => $thumbnailSuccess,
                'mid_success' => $midSuccess
            ]);
            
            // Even if thumbnails fail, we don't want to fail the entire upload
            // The original image was uploaded successfully
            return true;
        } else {
            // For local storage, create thumbnails
            $this->createLocalThumbnail($imageManager, $filename, 'thumbnail_', 150, 150);
            $this->createLocalThumbnail($imageManager, $filename, 'mid_', 300, 300);
            return true;
        }
    }

    /**
     * Create S3 thumbnail
     */
    protected function createS3Thumbnail($imageManager, $filename, $prefix, $width, $height)
    {
        try {
            $originalPath = 'uploads/' . $filename;
            $thumbnailPath = 'uploads/' . $prefix . $filename;
            
            // Add detailed logging for debugging
            \Log::info('Creating S3 thumbnail', [
                'original_path' => $originalPath,
                'thumbnail_path' => $thumbnailPath,
                'dimensions' => "{$width}x{$height}"
            ]);
            
            // Check if original file exists in S3
            if (!Storage::disk('s3')->exists($originalPath)) {
                \Log::error('Original file not found in S3', ['path' => $originalPath]);
                return false;
            }
            
            // Get original image from S3 with retry mechanism
            $maxRetries = 3;
            $imageContent = null;
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $imageContent = Storage::disk('s3')->get($originalPath);
                    
                    // Validate image content
                    if (empty($imageContent)) {
                        throw new \Exception('Empty image content retrieved from S3');
                    }
                    
                    // Check if content starts with valid image headers
                    $imageHeaders = ['PNG' => "\x89PNG", 'JPEG' => "\xFF\xD8\xFF", 'GIF' => 'GIF'];
                    $isValidImage = false;
                    
                    foreach ($imageHeaders as $type => $header) {
                        if (substr($imageContent, 0, strlen($header)) === $header) {
                            $isValidImage = true;
                            \Log::info('Valid image detected', ['type' => $type, 'size' => strlen($imageContent)]);
                            break;
                        }
                    }
                    
                    if (!$isValidImage) {
                        throw new \Exception('Invalid image format or corrupted content');
                    }
                    
                    // If we reach here, content is valid
                    break;
                    
                } catch (\Exception $e) {
                    \Log::warning("Attempt {$attempt} failed to retrieve image from S3", [
                        'error' => $e->getMessage(),
                        'path' => $originalPath
                    ]);
                    
                    if ($attempt === $maxRetries) {
                        throw $e;
                    }
                    
                    // Wait before retry
                    usleep(500000); // 0.5 second
                }
            }
            
            // Create thumbnail with better error handling
            try {
                $image = $imageManager->read($imageContent);
                
                // Resize with proper constraints
                $image->resize($width, $height, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
                
                // Convert to JPEG with quality control
                $thumbnailContent = $image->toJpeg(90);
                
                // Upload thumbnail to S3
                $uploadResult = Storage::disk('s3')->put($thumbnailPath, $thumbnailContent);
                
                if ($uploadResult) {
                    \Log::info('S3 thumbnail created successfully', [
                        'path' => $thumbnailPath,
                        'size' => strlen($thumbnailContent)
                    ]);
                    return true;
                } else {
                    throw new \Exception('Failed to upload thumbnail to S3');
                }
                
            } catch (\Exception $e) {
                \Log::error('Failed to process image for thumbnail', [
                    'error' => $e->getMessage(),
                    'image_size' => strlen($imageContent),
                    'original_path' => $originalPath
                ]);
                throw $e;
            }
            
        } catch (\Exception $e) {
            \Log::error('Failed to create S3 thumbnail', [
                'error' => $e->getMessage(),
                'filename' => $filename,
                'prefix' => $prefix,
                'dimensions' => "{$width}x{$height}",
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Create local thumbnail
     */
    protected function createLocalThumbnail($imageManager, $filename, $prefix, $width, $height)
    {
        try {
            $uploadsPath = public_path('uploads');
            $originalPath = $uploadsPath . '/' . $filename;
            $thumbnailPath = $uploadsPath . '/' . $prefix . $filename;
            
            // Create thumbnail
            $image = $imageManager->read($originalPath);
            $image->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            
            $image->save($thumbnailPath);
        } catch (\Exception $e) {
            \Log::error('Failed to create local thumbnail: ' . $e->getMessage());
        }
    }

    /**
     * Save to database
     */
    protected function saveToDatabase($data, $reference, $referenceType)
    {
        // Save to xref table
        $idxref = Xref::create([
            'object_type' => $data['object_type'],
            'object' => $data['path'],
            'reference_type' => $referenceType,
            'reference' => $reference
        ]);

        // Save to images table
        Images::create([
            'image' => $data['path'],
            'id_reference' => $reference,
            'reference_type' => $referenceType
        ]);

        return $idxref->idxref;
    }

    public function filename($tmp_name)
    {
        // Generate unique filename
        $finfo = new \finfo(FILEINFO_EXTENSION);
        $ext = $finfo->file($tmp_name);
        
        if (empty($ext)) {
            $ext = 'jpg'; // Default extension
        }
        
        return time() . '_' . mt_rand(100000, 999999) . '.' . $ext;
    }

    public function findImages($of_ref_type, $ref_id)
    {
        $images = Images::where('id_reference', $ref_id)
                       ->where('reference_type', $of_ref_type)
                       ->get();
        
        return $images;
    }

    public function deleteImage($idxref)
    {
        $xref = Xref::find($idxref);
        
        if ($xref) {
            $filename = $xref->object;
            
            // Delete files from storage
            if ($this->getS3Service()->isUsingS3()) {
                Storage::disk('s3')->delete('uploads/' . $filename);
                Storage::disk('s3')->delete('uploads/thumbnail_' . $filename);
                Storage::disk('s3')->delete('uploads/mid_' . $filename);
            } else {
                $uploadsPath = public_path('uploads');
                @unlink($uploadsPath . '/' . $filename);
                @unlink($uploadsPath . '/thumbnail_' . $filename);
                @unlink($uploadsPath . '/mid_' . $filename);
            }
            
            // Delete from database
            $xref->delete();
            
            Images::where('image', $filename)->delete();
            
            return true;
        }
        
        return false;
    }
}
