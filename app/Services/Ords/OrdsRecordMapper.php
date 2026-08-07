<?php

namespace App\Services\Ords;

use App\Helpers\Iso3166;
use App\Models\Device;
use Carbon\Carbon;

/**
 * Maps a Fixometer device row onto an Open Repair Data Standard v0.3 record.
 *
 * Expects the device to carry the aliased columns selected by
 * PublicRepairController::buildBaseRepairQuery and an eager-loaded `barriers`
 * relation.
 */
class OrdsRecordMapper
{
    public const UNASSIGNED_ID_PREFIX = 'UNASSIGNED_';

    public const STANDARD = 'Open Repair Data Standard v0.3';

    public const COLUMNS = [
        'id',
        'data_provider',
        'country',
        'partner_product_category',
        'product_category',
        'product_category_id',
        'brand',
        'year_of_manufacture',
        'product_age',
        'repair_status',
        'repair_barrier_if_end_of_life',
        'group_identifier',
        'event_date',
        'problem',
    ];

    public function __construct(private readonly ProblemTextScrubber $scrubber)
    {
    }

    public function scrubber(): ProblemTextScrubber
    {
        return $this->scrubber;
    }

    /** @return array<string,mixed> keyed in ORDS column order */
    public function map(Device $device): array
    {
        $eventDate = $this->eventDate($device);
        $productAge = $this->productAge($device);
        [$productCategory, $productCategoryId] = $this->productCategory($device);

        return [
            // Trimmed to match the controller's guard: untrimmed, " ifixit_" would
            // pass validation and emit ids with a leading space.
            'id' => trim((string) config('ords.id_prefix')) . $device->iddevices,
            'data_provider' => config('ords.data_provider'),
            'country' => Iso3166::alpha3($device->ords_country_code),
            'partner_product_category' => $this->partnerProductCategory($device),
            'product_category' => $productCategory,
            'product_category_id' => $productCategoryId,
            'brand' => $this->nullIfBlank($device->brand),
            'year_of_manufacture' => $this->yearOfManufacture($eventDate, $productAge),
            'product_age' => $productAge,
            'repair_status' => $this->repairStatus($device),
            'repair_barrier_if_end_of_life' => $this->repairBarrier($device),
            'group_identifier' => $this->nullIfBlank($device->ords_group_name),
            'event_date' => $eventDate?->toDateString(),
            'problem' => $this->problem($device),
        ];
    }

    /**
     * "<category> ~ <item_type>" when an item type is present, bare category
     * otherwise -- follows The Restart Project's own published row convention.
     */
    private function partnerProductCategory(Device $device): ?string
    {
        $category = $this->nullIfBlank($device->ords_category_name);
        $itemType = $this->nullIfBlank($device->item_type);

        if ($category === null) {
            return $itemType;
        }

        return $itemType === null ? $category : "{$category} ~ {$itemType}";
    }

    /** @return array{0: ?string, 1: ?int} [product_category, product_category_id] */
    private function productCategory(Device $device): array
    {
        $name = $this->nullIfBlank($device->ords_category_name);

        if (! $device->ords_category_powered) {
            // ORA's unpowered dataset carries no product_category_id.
            return [
                config('ords.categories_unpowered')[$name] ?? config('ords.categories_unpowered_fallback'),
                null,
            ];
        }

        // Unmapped powered category is a vocabulary gap, not a data error: fall
        // back to our own name with a null id rather than dropping the record.
        return config('ords.categories_powered')[$name] ?? [$name, null];
    }

    /**
     * `devices.age` is DECIMAL(5,2) UNSIGNED ZEROFILL NOT NULL DEFAULT 0, so
     * MySQL returns it zero-padded ("005.00") and 0 means "not recorded", not
     * a real age. is_numeric also covers instances still on the old free-text
     * VARCHAR column.
     */
    private function productAge(Device $device): int|float|null
    {
        $age = $device->age;

        if ($age === null || ! is_numeric($age)) {
            return null;
        }

        $age = (float) $age;

        if ($age <= 0) {
            return null;
        }

        return $age == (int) $age ? (int) $age : $age;
    }

    /**
     * Not stored, so derived: the year the event ran minus the item's age.
     * ORDS wants a 4-digit string; anything we cannot derive is omitted.
     */
    private function yearOfManufacture(?Carbon $eventDate, int|float|null $productAge): ?string
    {
        if ($eventDate === null || $productAge === null) {
            return null;
        }

        $year = (int) round($eventDate->year - $productAge);

        // ORDS constrains this to ^[0-9]{4}$; clamp rather than emit a rejected value.
        if ($year < 1000 || $year > 9999) {
            return null;
        }

        return (string) $year;
    }

    private function eventDate(Device $device): ?Carbon
    {
        $startUtc = $device->ords_event_start_utc;

        if (empty($startUtc)) {
            return null;
        }

        $date = Carbon::parse($startUtc, 'UTC');
        $timezone = $this->nullIfBlank($device->ords_event_timezone);

        if ($timezone !== null) {
            try {
                $date = $date->setTimezone($timezone);
            } catch (\Throwable) {
                // Unrecognised timezone: keep UTC rather than drop a required column.
            }
        }

        return $date;
    }

    private function repairStatus(Device $device): string
    {
        return config('ords.repair_status')[$device->repair_status]
            ?? config('ords.repair_status_unknown');
    }

    /** Devices can carry several barriers; ORDS has one column, so the first wins. */
    private function repairBarrier(Device $device): ?string
    {
        if ((int) $device->repair_status !== Device::REPAIR_STATUS_ENDOFLIFE) {
            return null;
        }

        $barrier = $device->barriers->first();

        if ($barrier === null) {
            return null;
        }

        return config('ords.barriers')[$barrier->barrier] ?? null;
    }

    private function problem(Device $device): ?string
    {
        if (! config('ords.problem.include')) {
            return null;
        }

        $problem = config('ords.problem.scrub')
            ? $this->scrubber->scrub($device->problem)
            : (string) $device->problem;

        return $this->nullIfBlank($problem);
    }

    private function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
