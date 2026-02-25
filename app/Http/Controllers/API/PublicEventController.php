<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Party as PartyResource;
use App\Models\Group;
use App\Models\Party;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicEventController extends Controller
{
    public function listEvents(Request $request): JsonResponse
    {
        return $this->listWithFilters($request);
    }

    public function showEvent(Request $request, int $id): JsonResponse
    {
        $query = $this->buildBaseEventQuery();
        $this->applyClientRestrictions($query, $request);

        $event = $query
            ->where('events.idevents', $id)
            ->firstOrFail();

        return response()->json([
            'data' => $this->toPublicEventArray($event),
        ]);
    }

    public function listGroupEvents(Request $request, int $id): JsonResponse
    {
        Group::findOrFail($id);

        return $this->listWithFilters($request, function (Builder $query) use ($id) {
            $query->where('events.group', $id);
        });
    }

    private function listWithFilters(Request $request, ?callable $filter = null): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date'],
            'updated_start' => ['nullable', 'date'],
            'updated_end' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $this->buildBaseEventQuery();
        $this->applyClientRestrictions($query, $request);

        if ($filter) {
            $filter($query);
        }

        $this->applyDateFilters($query, $validated);

        $maxUpdatedAt = (clone $query)->max('events.updated_at');

        $perPage = (int) ($validated['per_page'] ?? 50);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(function (Party $event) {
                return $this->toPublicEventArray($event);
            })->values(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'sync' => [
                'generated_at' => Carbon::now()->toIso8601String(),
                'max_updated_at' => $maxUpdatedAt ? Carbon::parse($maxUpdatedAt)->toIso8601String() : null,
            ],
        ]);
    }

    private function toPublicEventArray(Party $event): array
    {
        $data = PartyResource::make($event)->resolve();

        // Keep payload lightweight for third-party display use-cases.
        unset($data['stats'], $data['network_data']);

        if (isset($data['group']) && is_array($data['group'])) {
            unset($data['group']['networks']);
        }

        return $data;
    }

    private function buildBaseEventQuery(): Builder
    {
        return Party::query()
            ->join('groups', 'groups.idgroups', '=', 'events.group')
            ->whereNull('events.deleted_at')
            ->whereNull('groups.deleted_at')
            ->where('events.approved', true)
            ->where('groups.approved', true)
            ->distinct()
            ->select('events.*')
            ->orderBy('events.event_start_utc', 'asc');
    }

    private function applyClientRestrictions(Builder $query, Request $request): void
    {
        $client = $request->attributes->get('apiClient');
        $allowedNetworkIds = $client?->allowed_network_ids ?: [];

        if (!empty($allowedNetworkIds)) {
            $query->join('group_network as permitted_network', 'permitted_network.group_id', '=', 'groups.idgroups')
                ->whereIn('permitted_network.network_id', $allowedNetworkIds);
        }
    }

    private function applyDateFilters(Builder $query, array $validated): void
    {
        if (!empty($validated['start'])) {
            $start = Carbon::parse($validated['start'])->setTimezone('UTC')->toIso8601String();
            $query->where('events.event_start_utc', '>=', $start);
        } else {
            $query->where('events.event_end_utc', '>=', Carbon::now()->setTimezone('UTC')->toIso8601String());
        }

        if (!empty($validated['end'])) {
            $end = Carbon::parse($validated['end'])->setTimezone('UTC')->toIso8601String();
            $query->where('events.event_end_utc', '<=', $end);
        }

        if (!empty($validated['updated_start'])) {
            $updatedStart = Carbon::parse($validated['updated_start'])->setTimezone('UTC')->toDateTimeString();
            $query->where('events.updated_at', '>=', $updatedStart);
        }

        if (!empty($validated['updated_end'])) {
            $updatedEnd = Carbon::parse($validated['updated_end'])->setTimezone('UTC')->toDateTimeString();
            $query->where('events.updated_at', '<=', $updatedEnd);
        }
    }
}
