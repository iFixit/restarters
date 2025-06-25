<?php

namespace App\Helpers;

use App\Models\Images;
use App\Models\Xref;
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

    /**
     * Get the uploads disk configuration
     */
    private function getUploadsDisk()
    {
        return config('filesystems.disks.uploads.driver', 'local') === 's3' ? 's3_uploads' : 'public_uploads';
    }

    /**
     * Check if we're using S3 for uploads
     */
    private function isUsingS3()
    {
        return config('filesystems.disks.uploads.driver', 'local') === 's3';
    }

    /**
     * Get the upload path based on storage type
     */
    private function getUploadPath($filename = '')
    {
        if ($this->isUsingS3()) {
            return $filename;
        }
        return public_path('uploads') . ($filename ? '/' . $filename : '');
    }

    /**
     * Get the URL for uploaded files
     */
    private function getUploadUrl($filename)
    {
        if ($this->isUsingS3()) {
            return Storage::disk('s3_uploads')->url($filename);
        }
        return '/uploads/' . $filename;
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

        $isUsingS3 = config('filesystems.disks.uploads.driver', 'local') === 's3';
        
        if ($isUsingS3) {
            $prefix = '';
            if ($type === 'thumbnail') {
                $prefix = 'thumbnail_';
            } elseif ($type === 'mid') {
                $prefix = 'mid_';
            }
            
            // Check if CloudFront URL is configured
            $cloudfrontUrl = config('filesystems.disks.s3_uploads.cloudfront_url');
            if ($cloudfrontUrl) {
                // Use CloudFront for serving images
                $root = config('filesystems.disks.s3_uploads.root');
                $url = rtrim($cloudfrontUrl, '/') . '/' . ltrim($root, '/') . '/' . $prefix . $filename;
                return $url;
            }
            
            // Fallback to direct S3 URLs
            $url = Storage::disk('s3_uploads')->url($prefix . $filename);
            
            // Fix for path-style endpoints (LocalStack) - construct proper URL with bucket name
            if (config('filesystems.disks.s3_uploads.use_path_style_endpoint')) {
                $bucket = config('filesystems.disks.s3_uploads.bucket');
                $endpoint = config('filesystems.disks.s3_uploads.endpoint');
                $root = config('filesystems.disks.s3_uploads.root');
                
                // Build correct path-style URL: endpoint/bucket/root/file
                $url = rtrim($endpoint, '/') . '/' . $bucket . '/' . ltrim($root, '/') . '/' . $prefix . $filename;
            }
            
            return $url;
        } else {
            if ($type === 'thumbnail') {
                return asset('/uploads/thumbnail_' . $filename);
            } elseif ($type === 'mid') {
                return asset('/uploads/mid_' . $filename);
            }
            return asset('/uploads/' . $filename);
        }
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
                'storage_type' => $this->isUsingS3() ? 's3' : 'local',
                'reference' => $reference,
                'reference_type' => $referenceType
            ]);
            
            if ($this->isUsingS3()) {
                // Upload to S3
                $fileContents = file_get_contents($tmp_name);
                Storage::disk('s3_uploads')->put($filename, $fileContents);
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
                if (!$this->isUsingS3()) {
                    // Fix orientation for local files
                    $imageManager->read($this->path)->save($this->path);
                }
            }

            if ($type === 'image') {
                // Get image dimensions
                if ($this->isUsingS3()) {
                    // For S3, we need to get dimensions from the temporary file
                    $size = getimagesize($tmp_name);
                } else {
                    $size = getimagesize($this->path);
                }
                $data['width'] = $size[0];
                $data['height'] = $size[1];

                if ($profile) {
                    $data['alt_text'] = 'Profile Picture';
                }

                if ($data['width'] > $data['height']) {
                    $biggestSide = $data['width'];
                    $resize_height = true;
                } else {
                    $biggestSide = $data['height'];
                    $resize_height = false;
                }

                $thumbSize = 80;
                $midSize = 260;

                // In test mode, we create empty thumbnails to satisfy the code without Intervention
                if (FixometerFile::$uploadTesting) {
                    if ($this->isUsingS3()) {
                        // For S3 testing, just store the same file as thumbnails
                        Storage::disk('s3_uploads')->put('thumbnail_' . $filename, $fileContents);
                        Storage::disk('s3_uploads')->put('mid_' . $filename, $fileContents);
                    } else {
                        // Just copy the original file as thumbnails for testing
                        copy($this->path, $this->getUploadPath('thumbnail_' . $filename));
                        copy($this->path, $this->getUploadPath('mid_' . $filename));
                    }
                } else {
                    // Normal processing with Intervention Image
                    if ($this->isUsingS3()) {
                        // For S3, process from temporary file and upload results
                        $thumb = $imageManager->read($tmp_name);
                        $mid = $imageManager->read($tmp_name);

                        if ($resize_height) { // Resize before crop
                            $thumb->scale(null, $thumbSize);
                            $mid->scale(null, $midSize);
                        } else {
                            $thumb->scale($thumbSize, null);
                            $mid->scale($midSize, null);
                        }

                        if ($crop) {
                            $thumb->crop($thumbSize, $thumbSize);
                            $mid->crop($midSize, $midSize);
                        }

                        // Save to temporary files and upload to S3
                        $thumbTemp = tempnam(sys_get_temp_dir(), 'thumb_');
                        $midTemp = tempnam(sys_get_temp_dir(), 'mid_');
                        
                        $thumb->save($thumbTemp, 85);
                        $mid->save($midTemp, 85);
                        
                        Storage::disk('s3_uploads')->put('thumbnail_' . $filename, file_get_contents($thumbTemp));
                        Storage::disk('s3_uploads')->put('mid_' . $filename, file_get_contents($midTemp));
                        
                        // Clean up temporary files
                        unlink($thumbTemp);
                        unlink($midTemp);
                    } else {
                        // Local processing as before
                        $thumb = $imageManager->read($this->path);
                        $mid = $imageManager->read($this->path);

                        if ($resize_height) { // Resize before crop
                            $thumb->scale(null, $thumbSize);
                            $mid->scale(null, $midSize);
                        } else {
                            $thumb->scale($thumbSize, null);
                            $mid->scale($midSize, null);
                        }

                        if ($crop) {
                            $thumb->crop($thumbSize, $thumbSize);
                            $mid->crop($midSize, $midSize);
                        }

                        $thumb->save($this->getUploadPath('thumbnail_' . $filename), 85);
                        $mid->save($this->getUploadPath('mid_' . $filename), 85);
                    }
                }

                $this->table = 'images';
                $Images = new Images;

                $image = $Images->create($data)->id;

                if (is_numeric($image) && ! is_null($reference) && ! is_null($referenceType)) {
                    Xref::create([
                        'object' => $image,
                        'object_type' => env('TBL_IMAGES'),
                        'reference' => $reference,
                        'reference_type' => $referenceType,
                    ]);
                }
            }

            return $filename;
        }

        return null;
    }

    /**
     * generates filename and maintains
     * correct file extension
     * (MIME check with Finfo!)
     * */
    public function filename($tmp_name)
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $lext = array_search(
            $finfo->file($tmp_name),
            [
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
            ],
            true
        );

        if (empty($lext) || ! $lext || is_null($lext)) {
            return false;
        }
        $this->ext = $lext;

        return time().sha1_file($tmp_name).rand(1, 15000).'.'.$lext;
    }

    public function findImages($of_ref_type, $ref_id)
    {
        $sql = 'SELECT * FROM `images` AS `i`
                    INNER JOIN `xref` AS `x` ON `x`.`object` = `i`.`idimages`
                    WHERE `x`.`object_type` = '.env('TBL_IMAGES').' AND
                    `x`.`reference_type` = :refType AND
                    `x`.`reference` = :refId';

        try {
            return DB::select($sql, ['refType' => $of_ref_type, 'refId' => $ref_id]);
        } catch (\Illuminate\Database\QueryException $e) {
            return DB::db($e);
        }
    }

    public function deleteImage($idxref)
    {
        // Delete the xref.  This is sufficient to stop the image being attached to the device.  We leave the
        // file in existence in case we want it later for debugging/mining.
        $sql = 'DELETE FROM `xref` WHERE `idxref` = :id AND `object_type` = '.env('TBL_IMAGES');
        DB::delete($sql, ['id' => $idxref]);
    }
}
