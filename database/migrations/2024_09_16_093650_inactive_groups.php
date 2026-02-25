<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use query builder instead of Eloquent model to avoid SoftDeletes
        // scope referencing a deleted_at column that doesn't exist yet.
        $groups = DB::table('groups')
            ->join('grouptags_groups', 'groups.idgroups', '=', 'grouptags_groups.group')
            ->join('group_tags', 'grouptags_groups.group_tag', '=', 'group_tags.id')
            ->where('group_tags.id', \App\Models\GroupTags::INACTIVE)
            ->select('groups.*')
            ->get();

        foreach ($groups as $group) {
            $name = str_replace('[INACTIVE] ', '', $group->name);
            $name = str_replace('[INACTIVE]', '', $name);

            DB::table('groups')
                ->where('idgroups', $group->idgroups)
                ->update([
                    'archived_at' => $group->updated_at,
                    'name' => $name,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add [INACTIVE] into all groups with archived_at.
        $groups = DB::table('groups')->whereNotNull('archived_at')->get();

        foreach ($groups as $group) {
            DB::table('groups')
                ->where('idgroups', $group->idgroups)
                ->update(['name' => '[INACTIVE] ' . $group->name]);
        }
    }
};
