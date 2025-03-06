<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop discourse_logo from Network/Group table
        if (Schema::hasColumn('networks', 'discourse_logo')) {
            Schema::table('networks', function (Blueprint $table) {
                $table->dropColumn('discourse_logo');
            });
        }

        // Drop discourse_group from networks table
        if (Schema::hasColumn('networks', 'discourse_group')) {
            Schema::table('networks', function (Blueprint $table) {
                $table->dropColumn('discourse_group');
            });
        }

        // Drop discourse_group from groups table
        if (Schema::hasColumn('groups', 'discourse_group')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->dropColumn('discourse_group');
            });
        }

        // Drop discourse_thread from events table
        if (Schema::hasColumn('events', 'discourse_thread')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('discourse_thread');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Add discourse_logo to networks table
        Schema::table('networks', function (Blueprint $table) {
            $table->integer('discourse_logo')->nullable()->comment('ID of last Laravel Group image applied to Discourse Group');
        });

        // Add discourse_group to networks table
        Schema::table('networks', function (Blueprint $table) {
            $table->string('discourse_group', 255)->nullable();
        });

        // Add discourse_group to groups table
        Schema::table('groups', function (Blueprint $table) {
            $table->string('discourse_group', 255)->nullable();
        });

        // Add discourse_thread to events table
        Schema::table('events', function (Blueprint $table) {
            $table->string('discourse_thread', 255)->nullable();
        });
    }
};
