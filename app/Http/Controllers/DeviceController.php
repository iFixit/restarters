<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use App\Models\Brands;
use App\Models\Cluster;
use App\Models\Device;
use App\Events\DeviceCreatedOrUpdated;
use App\Models\EventsUsers;
use App\Models\Group;
use App\Helpers\Fixometer;
use App\Notifications\AdminAbnormalDevices;
use App\Models\Party;
use App\Models\User;
use App\Models\UserGroups;
use App\Models\Xref;
use Auth;
use App\Helpers\FixometerFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Lang;
use Notification;
use View;

class DeviceController extends Controller
{
    public function index($search = null): \Illuminate\View\View
    {
        $user = User::getProfile(Auth::id());
        $clusters = Cluster::with(['categories'])->get()->all();
        $brands = Brands::orderBy('brand_name', 'asc')->get()->all();

        $most_recent_finished_event = Party::with('theGroup')
        ->hasDevicesRepaired(1)
        ->eventHasFinished()
        ->orderBy('event_start_utc', 'DESC')
        ->first();

        if ($most_recent_finished_event) {
            $most_recent_finished_event['id_events'] = $most_recent_finished_event->idevents;
            $most_recent_finished_event['waste_prevented'] = $most_recent_finished_event->WastePrevented;
        }

        $global_impact_data = app(\App\Http\Controllers\ApiController::class)
                            ->homepage_data();
        $global_impact_data = $global_impact_data->getData();

        $user_groups = [];

        if ($user) {
            foreach (UserGroups::where('user', $user->id)->pluck('group')->toArray() as $gid) {
                $user_groups[] = Group::find($gid);
            }
        }

        return view('fixometer.index', [
            'user' => $user,
            'user_groups' => $user_groups,
            'most_recent_finished_event' => $most_recent_finished_event,
            'impact_data' => $global_impact_data,
            'clusters' => $clusters,
            'barriers' => \App\Helpers\Fixometer::allBarriers(),
            'brands' => $brands,
        ]);
    }

    public function imageUpload(Request $request, $id)
    {
        try {
            $images = [];

            if (isset($_FILES) && ! empty($_FILES)) {
                \Log::info('Processing image upload request', [
                    'device_id' => $id,
                    'files_count' => count($_FILES),
                    'files_structure' => array_keys($_FILES)
                ]);

                // Process files - handle both single and multiple file scenarios
                $uploadedCount = 0;
                $errors = [];
                
                foreach ($_FILES as $fieldName => $fileData) {
                    try {
                        // Check if this is a single file or multiple files
                        if (is_array($fileData['name'])) {
                            // Handle multiple files in array format
                            $fileCount = count($fileData['name']);
                            \Log::info('Processing multiple files', ['count' => $fileCount]);
                            
                            for ($i = 0; $i < $fileCount; $i++) {
                                if ($fileData['error'][$i] == UPLOAD_ERR_OK) {
                                    $success = $this->processSingleFile($fileData, $i, $id);
                                    if ($success) {
                                        $uploadedCount++;
                                    } else {
                                        $errors[] = "Failed to upload file: " . $fileData['name'][$i];
                                    }
                                }
                            }
                        } else {
                            // Handle single file
                            if ($fileData['error'] == UPLOAD_ERR_OK) {
                                $success = $this->processSingleFile($fileData, null, $id);
                                if ($success) {
                                    $uploadedCount++;
                                } else {
                                    $errors[] = "Failed to upload file: " . $fileData['name'];
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error processing file', [
                            'field' => $fieldName,
                            'error' => $e->getMessage()
                        ]);
                        $errors[] = "Error processing file: " . $e->getMessage();
                    }
                }

                \Log::info('Upload processing complete', [
                    'device_id' => $id,
                    'uploaded_count' => $uploadedCount,
                    'errors' => $errors
                ]);

                if ($uploadedCount === 0) {
                    return response()->json([
                        'success' => false,
                        'error' => !empty($errors) ? implode(', ', $errors) : __('devices.image_upload_error'),
                        'images' => []
                    ], 400);
                }

                // Get current images for this device
                if ($id > 0) {
                    $device = Device::findOrFail($id);
                    $images = $device->getImages();
                } else {
                    $File = new FixometerFile;
                    $images = $File->findImages(env('TBL_DEVICES'), $id);
                }

            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'No files uploaded',
                    'images' => []
                ], 400);
            }

            // Convert images to array format expected by frontend
            $imageArray = [];
            foreach ($images as $image) {
                $imageArray[] = [
                    'id' => $image->idimages,
                    'idxref' => $image->idxref ?? null,
                    'path' => $image->path,
                    'url' => \App\Helpers\FixometerFile::getUploadFileUrl($image->path),
                    'thumbnail_url' => \App\Helpers\FixometerFile::getUploadFileUrl($image->path, 'thumbnail'),
                    'mid_url' => \App\Helpers\FixometerFile::getUploadFileUrl($image->path, 'mid'),
                ];
            }

            return response()->json([
                'success' => true,
                'iddevices' => $id,
                'images' => $imageArray,
            ]);
        } catch (\Exception $e) {
            \Sentry\CaptureMessage("Image upload exception  " . $e->getMessage());
            \Log::error('Image upload error: ' . $e->getMessage(), [
                'device_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => __('devices.image_upload_error'),
                'images' => []
            ], 500);
        }
    }

    /**
     * Process a single file upload
     */
    private function processSingleFile($fileData, $index, $deviceId)
    {
        try {
            $file = new FixometerFile;
            
            if ($index !== null) {
                // Multiple files - create temporary $_FILES entry
                $tempFileKey = 'temp_file_' . $index . '_' . time();
                $_FILES[$tempFileKey] = [
                    'name' => $fileData['name'][$index],
                    'type' => $fileData['type'][$index],
                    'tmp_name' => $fileData['tmp_name'][$index],
                    'error' => $fileData['error'][$index],
                    'size' => $fileData['size'][$index],
                ];
                
                $result = $file->upload($tempFileKey, 'image', $deviceId, env('TBL_DEVICES'), true, false, true);
                
                // Clean up temporary $_FILES entry
                unset($_FILES[$tempFileKey]);
                
                return $result !== false;
            } else {
                // Single file - find the field name
                $fieldName = null;
                foreach ($_FILES as $key => $data) {
                    if ($data === $fileData) {
                        $fieldName = $key;
                        break;
                    }
                }
                
                if ($fieldName) {
                    $result = $file->upload($fieldName, 'image', $deviceId, env('TBL_DEVICES'), true, false, true);
                    return $result !== false;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            \Log::error('Error processing single file', [
                'device_id' => $deviceId,
                'index' => $index,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function deleteImage($device_id, $idxref): RedirectResponse
    {
        $user = Auth::user();

        if ($device_id > 0) {
            // We are deleting a photo from an existing device.
            $event_id = Device::find($device_id)->event;
            $in_event = EventsUsers::where('event', $event_id)->where('user', Auth::user()->id)->first();
            if (Fixometer::hasRole($user, 'Administrator') || is_object($in_event)) {
                $Image = new FixometerFile;
                $Image->deleteImage($idxref);

                return redirect()->back()->with('message', __('devices.image_delete_success'));
            }

            return redirect()->back()->with('message', __('devices.image_delete_error'));
        } else {
            // We are deleting a photo from a device which has not yet been added.
            //
            // There is a slight security issue here, in that one user could delete the photos from devices which
            // are in the process of being added by another user.  The chances of this being a real issue are very low.
            $Image = new FixometerFile;
            $Image->deleteImage($idxref);

            return redirect()->back()->with('message', __('devices.image_delete_success'));
        }
    }
}
