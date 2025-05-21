<?php

namespace App\Observers;

use App\Models\Device;

class DeviceObserver
{
    /**
     * Handle the Device "creating" event.
     */
    public function creating(Device $device): void
    {
        $this->syncRepairStatusStr($device);
    }

    /**
     * Handle the Device "updating" event.
     */
    public function updating(Device $device): void
    {
        $this->syncRepairStatusStr($device);
    }

    /**
     * Synchronize the repair_status_str field based on repair_status
     */
    private function syncRepairStatusStr(Device $device): void
    {
        if ($device->isDirty('repair_status')) {
            $device->repair_status_str = match ($device->repair_status) {
                Device::REPAIR_STATUS_FIXED => Device::REPAIR_STATUS_FIXED_STR,
                Device::REPAIR_STATUS_REPAIRABLE => Device::REPAIR_STATUS_REPAIRABLE_STR,
                Device::REPAIR_STATUS_ENDOFLIFE => Device::REPAIR_STATUS_ENDOFLIFE_STR,
                default => 'Unknown',
            };
        }
    }
} 