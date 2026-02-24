<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Party;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EventsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Party::with(['theGroup']);

            // Apply search filter
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('venue', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%");
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
                // Default shows only non-deleted
            }

            // Handle sorting
            $sortBy = $request->input('sort_by', 'event_start_utc');
            $sortDirection = $request->input('sort_direction', 'desc');

            if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
                $sortDirection = 'desc';
            }

            $sortableColumns = [
                'event_start_utc' => 'event_start_utc',
                'venue' => 'venue',
                'location' => 'location',
                'created_at' => 'created_at',
            ];

            $sortColumn = $sortableColumns[$sortBy] ?? 'event_start_utc';
            $query->orderBy($sortColumn, $sortDirection);

            $perPage = min($request->input('per_page', 100), 500);
            $events = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $events->getCollection(),
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'from' => $events->firstItem(),
                'to' => $events->lastItem(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching events: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch events',
            ], 500);
        }
    }

    public static function performSingleAction(int $event_id, string $action): JsonResponse
    {
        try {
            $event = Party::withTrashed()->findOrFail($event_id);
            $result = self::performAction($event, $action);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'event' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Error performing event action: ' . $e->getMessage());

            $statusCode = $e->getCode() === 409 ? 409 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    private static function performAction(Party $event, string $action): array
    {
        switch ($action) {
            case 'restore':
                // Check if parent group is soft-deleted
                $group = Group::withTrashed()->find($event->group);
                if ($group && $group->trashed()) {
                    throw new \Exception("Cannot restore event: the parent group '{$group->name}' is deleted. Restore the group first.", 409);
                }

                $event->restore();
                break;

            default:
                throw new \Exception("Invalid action: {$action}");
        }

        return [
            'id' => $event->idevents,
            'venue' => $event->venue,
            'message' => "Event has been {$action}d successfully.",
        ];
    }
}
