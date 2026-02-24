<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Group;
use Illuminate\Support\Facades\DB;
use App\Models\Network;

class GroupsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Group::with(['networks', 'group_tags'])
                ->withCount(['allConfirmedHosts', 'allConfirmedRestarters']);

            // Apply search filter
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%")
                      ->orWhere('postcode', 'like', "%{$search}%")
                      ->orWhere('area', 'like', "%{$search}%");
                });
            }

            // Apply deleted filter
            if ($request->filled('deleted')) {
                $deletedFilter = $request->input('deleted');
                if ($deletedFilter === 'only') {
                    $query->onlyTrashed();
                } elseif ($deletedFilter === 'all') {
                    $query->withTrashed();
                }
                // Default (no filter or 'active') shows only non-deleted
            }

            // Handle sorting
            $sortBy = $request->input('sort_by', 'name');
            $sortDirection = $request->input('sort_direction', 'asc');
            
            // Validate sort direction
            if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
                $sortDirection = 'asc';
            }
            
            // Map frontend column names to database columns
            $sortableColumns = [
                'name' => 'name',
                'location' => 'location',
                'confirmed_hosts_count' => 'all_confirmed_hosts_count',
                'confirmed_restarters_count' => 'all_confirmed_restarters_count',
                'approved' => 'approved',
                'created_at' => 'created_at',
            ];
            
            // Default to name if invalid sort field
            $sortColumn = $sortableColumns[$sortBy] ?? 'name';
            
            $query->orderBy($sortColumn, $sortDirection);

            $perPage = min($request->input('per_page', 100), 500);
            $groups = $query->paginate($perPage);

            // Transform data for frontend
            $transformedGroups = $groups->getCollection()->map(function ($group) {
                return $this->transformGroup($group);
            });

            return response()->json([
                'success' => true,
                'data' => $transformedGroups,
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
                'from' => $groups->firstItem(),
                'to' => $groups->lastItem(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching groups: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch groups',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public static function performSingleAction(int $group_id, string $action): JsonResponse
    {
        try {
            $group = Group::withTrashed()->findOrFail($group_id);

            $result = self::performAction($group, $action);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'group' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error performing action: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public static function performBulkActions(Request $request, string $action): JsonResponse
    {
        try {
            $group_ids = $request->input('group_ids');
            $groups = Group::withTrashed()->whereIn('idgroups', $group_ids)->get();

            $failedGroups = [];

            foreach ($groups as $group) {
                try {
                    self::performAction($group, $action);
                } catch (\Exception $e) {
                    $failedGroups[] = $e->getMessage();
                }
            }

            if (count($failedGroups) > 0) {
                throw new \Exception(implode(', ', $failedGroups));
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk actions performed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error performing bulk actions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform bulk actions. ' . $e->getMessage()
            ], 500);
        }
    }

    private static function performAction(Group $group, string $action): array
    {
        switch ($action) {
            case 'approve':
                $group->approved = true;
                $group->save();
                break;

            case 'unapprove':
                $group->approved = false;
                $group->save();
                break;

            case 'archive':
                $group->archived_at = now();
                $group->save();
                break;

            case 'unarchive':
                $group->archived_at = null;
                $group->save();
                break;

            case 'delete':
                // Soft-delete all group events (preserving devices and volunteer data)
                \App\Models\Party::where('events.group', $group->idgroups)->each(function ($event) {
                    $event->delete();
                });
                $group->delete();
                break;

            case 'restore':
                if (!$group->trashed()) {
                    break; // Skip non-deleted groups silently (relevant for bulk actions)
                }
                $group->restore();
                // Also restore soft-deleted events for this group
                \App\Models\Party::withTrashed()
                    ->where('events.group', $group->idgroups)
                    ->whereNotNull('events.deleted_at')
                    ->each(function ($event) {
                        $event->restore();
                    });
                break;

            default:
                throw new \Exception("Invalid action: {$action}");
        }

        return [
            'id' => $group->idgroups,
            'name' => $group->name,
            'message' => "Group '{$group->name}' has been {$action} successfully."
        ];
    }

    public static function importGroups(Request $request): JsonResponse
    {
        try {
            $file = $request->file('csv_file');
            $path = $file->getRealPath();
            $data = array_map('str_getcsv', file($path));
            $headers = array_shift($data);

            // Validate headers
            $requiredHeaders = ['Name', 'Location'];
            $missingHeaders = array_diff($requiredHeaders, $headers);
            
            if (!empty($missingHeaders)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required columns: ' . implode(', ', $missingHeaders)
                ], 422);
            }

            $created = 0;
            $errors = [];

            DB::transaction(function() use ($data, $headers, &$created, &$errors) {
                foreach ($data as $rowIndex => $row) {
                    if (empty(array_filter($row))) continue; // Skip empty rows

                    try {
                        $groupData = array_combine($headers, $row);
                        
                        // Validate required fields
                        if (empty($groupData['Name']) || empty($groupData['Location'])) {
                            $errors[] = "Row " . ($rowIndex + 2) . ": Name and Location are required";
                            continue;
                        }

                        // Create group
                        $group = new Group();
                        $group->name = $groupData['Name'];
                        $group->location = $groupData['Location'];
                        $group->postcode = $groupData['Postcode'] ?? null;
                        $group->area = $groupData['Area'] ?? null;
                        $group->country_code = $groupData['Country Code'] ?? null;
                        $group->latitude = $groupData['Latitude'] ?? null;
                        $group->longitude = $groupData['Longitude'] ?? null;
                        $group->website = $groupData['Website'] ?? null;
                        $group->phone = $groupData['Phone'] ?? null;
                        $group->email = $groupData['Email'] ?? null;
                        $group->free_text = $groupData['Description'] ?? null;
                        $group->approved = false; // New groups require approval
                        $group->save();

                        // Handle networks if provided
                        if (!empty($groupData['Networks'])) {
                            $networkNames = array_map('trim', explode(',', $groupData['Networks']));
                            $networkIds = Network::whereIn('name', $networkNames)->pluck('id');
                            if ($networkIds->isNotEmpty()) {
                                $group->networks()->attach($networkIds);
                            }
                        }

                        $created++;

                    } catch (\Exception $e) {
                        $errors[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                    }
                }
            });

            if (count($errors) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process CSV file. ' . implode(', ', $errors),
                    'errors' => $errors
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully created {$created} groups.",
                'data' => [
                    'created' => $created,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error uploading CSV: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process CSV file'
            ], 500);
        }
    }

    public static function exportGroups(Request $request)
    {
        try {
            // Build query with same logic as index method
            $query = Group::with(['networks', 'group_tags']);

            // Apply search filter
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%")
                      ->orWhere('postcode', 'like', "%{$search}%")
                      ->orWhere('area', 'like', "%{$search}%");
                });
            }

            // Apply status filter
            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status === 'approved') {
                    $query->where('approved', true);
                } elseif ($status === 'pending') {
                    $query->where('approved', false);
                }
            }

            // Apply archived filter
            if ($request->filled('archived')) {
                $archived = $request->input('archived');
                if ($archived === 'yes') {
                    $query->whereNotNull('archived_at');
                } elseif ($archived === 'no') {
                    $query->whereNull('archived_at');
                }
            }

            // Apply network filter
            if ($request->filled('network')) {
                $networkId = $request->input('network');
                $query->whereHas('networks', function($q) use ($networkId) {
                    $q->where('networks.id', $networkId);
                });
            }

            // Apply country filter
            if ($request->filled('country')) {
                $countryCode = $request->input('country');
                $query->where('country_code', $countryCode);
            }

            $groups = $query->orderBy('name', 'asc')->get();

            // Create CSV content
            $filename = 'groups_export_' . date('Y-m-d_H-i-s') . '.csv';
            $csvContent = self::generateCsvContent($groups);

            // Return CSV as download
            return response($csvContent)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            Log::error('Error exporting groups CSV: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export groups'
            ], 500);
        }
    }


    private static function generateCsvContent($groups)
    {
        $csvData = [];
        
        // Add headers
        $csvData[] = [
            'ID', 'Name', 'Location', 'Postcode', 'Area', 'Country Code',
            'Latitude', 'Longitude', 'Website', 'Phone', 'Email',
            'Approved', 'Archived', 'Networks', 'Tags', 'Description',
            'Hosts Count', 'Restarters Count', 'Created At'
        ];

        // Add data rows
        foreach ($groups as $group) {
            // Get country name from country code
            $countryDisplay = null;
            if ($group->country_code) {
                $countryDisplay = \App\Helpers\Fixometer::getCountryFromCountryCode($group->country_code);
            }

            // Manually calculate counts
            $confirmedHostsCount = $group->allConfirmedHosts()->count();
            $confirmedRestartersCount = $group->allConfirmedRestarters()->count();

            $csvData[] = [
                $group->idgroups,
                $group->name,
                $group->location,
                $group->postcode,
                $group->area,
                $group->country_code,
                $group->latitude,
                $group->longitude,
                $group->website,
                $group->phone,
                $group->email,
                $group->approved ? 'Yes' : 'No',
                $group->archived_at ? 'Yes' : 'No',
                $group->networks->pluck('name')->join(', '),
                $group->group_tags->pluck('tag_name')->join(', '),
                $group->free_text,
                $confirmedHostsCount,
                $confirmedRestartersCount,
                $group->created_at ? $group->created_at->format('Y-m-d H:i:s') : ''
            ];
        }

        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * Transform group data for frontend
     */
    private function transformGroup($group): array
    {
        // Get country name from country code
        $countryDisplay = null;
        if ($group->country_code) {
            $countryDisplay = \App\Helpers\Fixometer::getCountryFromCountryCode($group->country_code);
        }

        return [
            'idgroups' => $group->idgroups,
            'name' => $group->name,
            'location' => $group->location,
            'postcode' => $group->postcode,
            'area' => $group->area,
            'country_code' => $group->country_code,
            'country_display' => $countryDisplay,
            'approved' => (bool) $group->approved,
            'archived_at' => $group->archived_at,
            'deleted_at' => $group->deleted_at,
            'created_at' => $group->created_at,
            'networks' => $group->networks,
            'group_tags' => $group->group_tags,
            'confirmed_hosts_count' => $group->all_confirmed_hosts_count,
            'confirmed_restarters_count' => $group->all_confirmed_restarters_count,
        ];
    }
} 