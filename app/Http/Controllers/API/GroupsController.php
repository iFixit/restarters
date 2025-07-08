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

    public static function performSingleAction(int $group_id, string $action): JsonResponse
    {
        try {
            $group = Group::findOrFail($group_id);

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
                'message' => 'Failed to perform action'
            ], 500);
        }
    }

    public static function performBulkActions(Request $request, string $action): JsonResponse
    {
        try {
            $group_ids = $request->input('group_ids');
            $groups = Group::whereIn('idgroups', $group_ids)->get();

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
                $groupName = $group->name;
                if (!$group->canDelete()) {
                    throw new \Exception("Group '{$groupName}' cannot be deleted because it has events with devices.");
                }
                $group->delete();
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