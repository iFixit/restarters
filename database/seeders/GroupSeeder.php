<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Group;
use Illuminate\Support\Facades\DB;

class GroupSeeder extends Seeder
{
    /**
     * Seed the Group table with one group.
     *
     * @return void
     */
    public function run()
    {
        // Create one group
        DB::table('groups')->insert([
            'name' => 'Restart Project Demo Group',
            'location' => 'London, UK',
            'latitude' => '51.5074',
            'longitude' => '-0.1278',
            'area' => 'Central London',
            'free_text' => 'This is a demo group for testing purposes. We focus on repairing electronics and reducing e-waste.',
            'approved' => 1,
            'country_code' => 'GB',
            'postcode' => 'EC1V 9HX',
            'timezone' => 'Europe/London',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
} 