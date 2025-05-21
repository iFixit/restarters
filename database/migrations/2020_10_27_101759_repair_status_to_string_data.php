<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('devices', 'repair_status_str')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->enum('repair_status_str', ['Unknown', 'Fixed', 'Repairable', 'End of life'])->after('repair_status')->index();
            });
        }
        
        // Initial population of repair_status_str from repair_status
        // Sync between numeric and string values will be handled at the application level
        // to avoid permission issues with functions and triggers in production

        DB::table('devices')
                ->where('repair_status', 1)
                ->update(['repair_status_str' => 'Fixed']);
        DB::table('devices')
                ->where('repair_status', 2)
                ->update(['repair_status_str' => 'Repairable']);
        DB::table('devices')
                ->where('repair_status', 3)
                ->update(['repair_status_str' => 'End of life']);
        DB::table('devices')
                ->where('repair_status', 0)
                ->update(['repair_status_str' => 'Unknown']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('devices', 'repair_status_str')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->dropColumn('repair_status_str');
            });
        }
    }
};
