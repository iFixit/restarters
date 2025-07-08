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

            $perPage = min($request->input('per_page', 100), 500);
            $groups = $query->paginate($perPage);

            // Transform data for frontend
            $transformedGroups = $groups->getCollection()->map(function ($group) {
                return $this->transformGroup($group);
            });

            return response()->json([
                'success' => true,
                'data' => $transformedGroups,
                'pagination' => [
                    'current_page' => $groups->currentPage(),
                    'last_page' => $groups->lastPage(),
                    'per_page' => $groups->perPage(),
                    'total' => $groups->total(),
                    'from' => $groups->firstItem(),
                    'to' => $groups->lastItem(),
                ]
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
            'all_confirmed_hosts_count' => $group->all_confirmed_hosts_count,
            'all_confirmed_restarters_count' => $group->all_confirmed_restarters_count,
        ];
    }
} 