<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Ords\OrdsRecordMapper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use League\Csv\EscapeFormula;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Open Repair Data Standard v0.3 export for the Open Repair Alliance.
 *
 * Visibility mirrors PublicEventController::buildBaseEventQuery.
 *
 * @see config/ords.php for the vocabulary maps and the id namespace guard.
 */
class PublicRepairController extends Controller
{
    public function __construct(private readonly OrdsRecordMapper $mapper)
    {
    }

    public function listRepairs(Request $request): Response
    {
        if ($guard = $this->guardExportConfig()) {
            return $guard;
        }

        $this->normalisePoweredInput($request);

        $validated = $request->validate([
            'format' => ['nullable', 'string', 'in:json,csv'],
            'updated_since' => ['nullable', 'date'],
            'event_start' => ['nullable', 'date'],
            'event_end' => ['nullable', 'date'],
            'powered' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            // Cast with a literal fallback: a null config value would build the rule "max:" and 500 every request.
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . (int) config('ords.pagination.max_per_page', 1000)],
        ]);

        $query = $this->buildBaseRepairQuery();
        $this->applyClientRestrictions($query, $request);
        $this->applyFilters($query, $validated);

        $maxUpdatedAt = (clone $query)->max('devices.updated_at');

        $perPage = (int) ($validated['per_page'] ?? config('ords.pagination.default_per_page'));
        $paginator = $query->paginate($perPage);

        $this->mapper->scrubber()->reset();
        $records = $paginator->getCollection()
            ->map(fn (Device $device) => $this->mapper->map($device))
            ->values();

        $this->logRedactions($request, $paginator);

        if (($validated['format'] ?? 'json') === 'csv') {
            return $this->csvResponse($records->all(), $paginator, $maxUpdatedAt);
        }

        return $this->jsonResponse($records->all(), $paginator, $maxUpdatedAt);
    }

    /** Laravel's `boolean` rule rejects "true"/"false"; normalise those before validation. */
    private function normalisePoweredInput(Request $request): void
    {
        if (!$request->has('powered')) {
            return;
        }

        $raw = $request->input('powered');

        // filter_var maps "" and null to false rather than firing
        // FILTER_NULL_ON_FAILURE, so an empty `?powered=` would narrow the
        // export to unpowered items. Null instead, which applyFilters skips.
        if ($raw === null || $raw === '') {
            $request->merge(['powered' => null]);

            return;
        }

        $normalised = filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($normalised !== null) {
            $request->merge(['powered' => $normalised]);
        }
    }

    /**
     * `id_prefix` and `data_provider` are instance-specific with no safe
     * default. Both are checked for emptiness, not just presence, because an
     * env var set to "" yields an empty string rather than falling back to
     * any default.
     */
    private function guardExportConfig(): ?JsonResponse
    {
        $prefix = trim((string) config('ords.id_prefix'));

        if ($prefix === '' || $prefix === OrdsRecordMapper::UNASSIGNED_ID_PREFIX) {
            return response()->json([
                'message' => 'ORDS export is not configured: no partner id namespace has been assigned.',
            ], 503);
        }

        if (trim((string) config('ords.data_provider')) === '') {
            return response()->json([
                'message' => 'ORDS export is not configured: no data provider name has been set.',
            ], 503);
        }

        return null;
    }

    private function jsonResponse(array $records, LengthAwarePaginator $paginator, $maxUpdatedAt): JsonResponse
    {
        return response()->json([
            'data' => $records,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'standard' => OrdsRecordMapper::STANDARD,
                'columns' => OrdsRecordMapper::COLUMNS,
            ],
            'sync' => [
                'generated_at' => Carbon::now()->toIso8601String(),
                'max_updated_at' => $maxUpdatedAt ? Carbon::parse($maxUpdatedAt)->toIso8601String() : null,
            ],
        ]);
    }

    /** No envelope for pagination/sync metadata in CSV, so it travels in headers instead. */
    private function csvResponse(array $records, LengthAwarePaginator $paginator, $maxUpdatedAt): StreamedResponse
    {
        return response()->streamDownload(function () use ($records) {
            $csv = Writer::createFromStream(fopen('php://output', 'w'));
            // `problem` is free text; a cell opening with = + - @ executes as a formula on open.
            $csv->addFormatter([new EscapeFormula(), 'escapeRecord']);
            $csv->insertOne(OrdsRecordMapper::COLUMNS);

            foreach ($records as $record) {
                // ORDS uses an empty string for every missing value.
                $csv->insertOne(array_map(
                    fn ($value) => $value === null ? '' : $value,
                    $record
                ));
            }
        }, 'ords-repairs.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Total-Count' => (string) $paginator->total(),
            'X-Page' => (string) $paginator->currentPage(),
            'X-Per-Page' => (string) $paginator->perPage(),
            'X-Last-Page' => (string) $paginator->lastPage(),
            'X-Max-Updated-At' => $maxUpdatedAt ? Carbon::parse($maxUpdatedAt)->toIso8601String() : '',
        ]);
    }

    private function buildBaseRepairQuery(): Builder
    {
        return Device::query()
            ->join('events', 'events.idevents', '=', 'devices.event')
            ->join('groups', 'groups.idgroups', '=', 'events.group')
            ->join('categories', 'categories.idcategories', '=', 'devices.category')
            ->whereNull('events.deleted_at')
            ->whereNull('groups.deleted_at')
            ->where('events.approved', true)
            ->where('groups.approved', true)
            // Ordered: the mapper emits only the first barrier, and an unordered
            // relation would let a device publish a different one between exports.
            ->with(['barriers' => fn ($q) => $q->orderBy('barriers.id')])
            ->select(
                'devices.*',
                'categories.name as ords_category_name',
                'categories.powered as ords_category_powered',
                'groups.name as ords_group_name',
                'groups.country_code as ords_country_code',
                'events.event_start_utc as ords_event_start_utc',
                'events.timezone as ords_event_timezone',
            )
            // Stable ordering so pagination can't skip or repeat rows between pages.
            ->orderBy('devices.iddevices', 'asc');
    }

    /**
     * Subquery, not a join: a group in several allowed networks would
     * otherwise multiply its devices across the result.
     */
    private function applyClientRestrictions(Builder $query, Request $request): void
    {
        $client = $request->attributes->get('apiClient');
        $allowedNetworkIds = $client?->allowed_network_ids ?: [];

        if (!empty($allowedNetworkIds)) {
            $query->whereIn('groups.idgroups', function ($sub) use ($allowedNetworkIds) {
                $sub->select('group_id')
                    ->from('group_network')
                    ->whereIn('network_id', $allowedNetworkIds);
            });
        }
    }

    private function applyFilters(Builder $query, array $validated): void
    {
        if (!empty($validated['updated_since'])) {
            $updatedSince = Carbon::parse($validated['updated_since'])->setTimezone('UTC')->toDateTimeString();
            $query->where('devices.updated_at', '>=', $updatedSince);
        }

        if (!empty($validated['event_start'])) {
            $start = Carbon::parse($validated['event_start'])->setTimezone('UTC')->toIso8601String();
            $query->where('events.event_start_utc', '>=', $start);
        }

        if (!empty($validated['event_end'])) {
            $end = Carbon::parse($validated['event_end']);

            // A date-only bound reads as "include that day"; left at 00:00 it'd exclude it entirely.
            if ($end->format('H:i:s') === '00:00:00') {
                $end = $end->endOfDay();
            }

            $query->where('events.event_start_utc', '<=', $end->setTimezone('UTC')->toIso8601String());
        }

        // Unfiltered by default: ORA publishes powered/unpowered as separate datasets.
        if (array_key_exists('powered', $validated) && $validated['powered'] !== null) {
            $query->where('categories.powered', (bool) $validated['powered']);
        }
    }

    private function logRedactions(Request $request, LengthAwarePaginator $paginator): void
    {
        $scrubber = $this->mapper->scrubber();

        // Also covers scrubbing-disabled: the count is 0 when the scrubber never ran.
        if ($scrubber->totalRedactions() === 0) {
            return;
        }

        $client = $request->attributes->get('apiClient');

        Log::info('ORDS export problem-text redactions', [
            'api_client_id' => $client?->id,
            'page' => $paginator->currentPage(),
            'records' => $paginator->count(),
            'redactions' => $scrubber->counts(),
        ]);
    }
}
