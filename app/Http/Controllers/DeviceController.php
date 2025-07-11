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
                // Check if we have multiple files
                $fileCount = 0;
                $fileKeys = [];
                
                foreach ($_FILES as $key => $file) {
                    if (is_array($file['name'])) {
                        $fileCount += count($file['name']);
                        $fileKeys[] = $key;
                    } else {
                        $fileCount++;
                        $fileKeys[] = $key;
                    }
                }
                
                \Log::info('Processing image upload', [
                    'device_id' => $id,
                    'file_count' => $fileCount,
                    'file_keys' => $fileKeys,
                    'files_structure' => array_keys($_FILES)
                ]);

                $uploadedCount = 0;
                
                // Process each file
                foreach ($_FILES as $fieldName => $fileData) {
                    if (is_array($fileData['name'])) {
                        // Multiple files in array format (file[0], file[1], etc.)
                        for ($i = 0; $i < count($fileData['name']); $i++) {
                            if ($fileData['error'][$i] == UPLOAD_ERR_OK) {
                                // Create a temporary $_FILES entry for this specific file
                                $tempFileKey = 'temp_file_' . $i;
                                $_FILES[$tempFileKey] = [
                                    'name' => $fileData['name'][$i],
                                    'type' => $fileData['type'][$i],
                                    'tmp_name' => $fileData['tmp_name'][$i],
                                    'error' => $fileData['error'][$i],
                                    'size' => $fileData['size'][$i],
                                ];
                                
                                $file = new FixometerFile;
                                $fn = $file->upload($tempFileKey, 'image', $id, env('TBL_DEVICES'), true, false, true);
                                
                                if ($fn) {
                                    $uploadedCount++;
                                    \Log::info('Successfully uploaded file', [
                                        'device_id' => $id,
                                        'file_name' => $fileData['name'][$i],
                                        'file_index' => $i,
                                        'upload_result' => $fn
                                    ]);
                                } else {
                                    \Log::error('Failed to upload file', [
                                        'device_id' => $id,
                                        'file_name' => $fileData['name'][$i],
                                        'file_index' => $i
                                    ]);
                                }
                                
                                // Clean up temporary $_FILES entry
                                unset($_FILES[$tempFileKey]);
                            }
                        }
                    } else {
                        // Single file
                        if ($fileData['error'] == UPLOAD_ERR_OK) {
                            $file = new FixometerFile;
                            $fn = $file->upload($fieldName, 'image', $id, env('TBL_DEVICES'), true, false, true);
                            
                            if ($fn) {
                                $uploadedCount++;
                                \Log::info('Successfully uploaded single file', [
                                    'device_id' => $id,
                                    'file_name' => $fileData['name'],
                                    'upload_result' => $fn
                                ]);
                            } else {
                                \Log::error('Failed to upload single file', [
                                    'device_id' => $id,
                                    'file_name' => $fileData['name']
                                ]);
                            }
                        }
                    }
                }

                \Log::info('Upload processing complete', [
                    'device_id' => $id,
                    'total_files' => $fileCount,
                    'uploaded_count' => $uploadedCount
                ]);

                // Get current images for this device
                if ($id > 0) {
                    $device = Device::findOrFail($id);
                    $images = $device->getImages();
                } else {
                    $File = new FixometerFile;
                    $images = $File->findImages(env('TBL_DEVICES'), $id);
                }

                if ($uploadedCount === 0) {
                    return response()->json([
                        'success' => false,
                        'error' => __('devices.image_upload_error'),
                        'images' => []
                    ], 400);
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

            // Return the current set of images for this device so that the client doesn't need to merge.
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
