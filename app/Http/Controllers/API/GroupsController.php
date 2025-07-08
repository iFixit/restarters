<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Group;

class GroupsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Group::with(['networks', 'group_tags'])
                ->withCount(['allConfirmedHosts', 'allConfirmedRestarters']);

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

    public static function performAction(int $group_id, string $action): JsonResponse
    {
        try {
            $group = Group::findOrFail($group_id);
     
            switch ($action) {
                case 'approve':
                    $group->approved = true;
                    $group->save();
                    $message = "Group '{$group->name}' has been approved successfully.";
                    break;

                case 'unapprove':
                    $group->approved = false;
                    $group->save();
                    $message = "Group '{$group->name}' has been unapproved successfully.";
                    break;
    
                case 'archive':
                    $group->archived_at = now();
                    $group->save();
                    $message = "Group '{$group->name}' has been archived successfully.";
                    break;
    
                case 'unarchive':
                    $group->archived_at = null;
                    $group->save();
                    $message = "Group '{$group->name}' has been unarchived successfully.";
                    break;
    
                case 'delete':
                    $groupName = $group->name;
                    if (!$group->canDelete()) {
                        throw new \Exception("Group '{$groupName}' cannot be deleted because it has events with devices.");
                    }
                    $group->delete();
                    $message = "Group '{$groupName}' has been deleted successfully.";
                    break;
    
                default:
                    throw new \Exception("Invalid action: {$action}");
            }
    
            $result = [
                'message' => $message,
                'group' => $action === 'delete' ? 
                    ['id' => $group->idgroups, 'deleted' => true] : 
                    [
                        'id' => $group->idgroups,
                        'name' => $group->name,
                        'approved' => (bool) $group->approved,
                        'archived' => $group->archived_at !== null,
                        'deleted' => false
                    ]
            ];

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'group' => $result['group']
            ]);

        } catch (\Exception $e) {
            Log::error('Error performing action: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform action'
            ], 500);
        }
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
            'created_at' => $group->created_at,
            'networks' => $group->networks,
            'group_tags' => $group->group_tags,
            'confirmed_hosts_count' => $group->all_confirmed_hosts_count,
            'confirmed_restarters_count' => $group->all_confirmed_restarters_count,
        ];
    }
} 