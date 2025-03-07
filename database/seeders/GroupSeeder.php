<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Group;
use App\Party;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GroupSeeder extends Seeder
{
    /**
     * Seed the Group table with groups and events.
     *
     * @return void
     */
    public function run()
    {
        // First group - without discourse data
        $this->createFirstGroup();
        
        // Second group - with discourse data
        $this->createSecondGroup();
    }
    
    /**
     * Create the first group and its event
     */
    private function createFirstGroup()
    {
        // Check if the group already exists
        $existingGroup = DB::table('groups')->where('name', 'Restart Project Demo Group')->first();
        
        if ($existingGroup) {
            $groupId = $existingGroup->idgroups;
            $this->command->info('Group "Restart Project Demo Group" already exists. Using existing group.');
        } else {
            // Create one group
            $groupId = DB::table('groups')->insertGetId([
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
            $this->command->info('Group "Restart Project Demo Group" created successfully.');
        }

        // Create an event for the group
        // Event will be scheduled for 2 weeks from now
        $eventDate = Carbon::now()->addWeeks(2);
        $startTime = Carbon::createFromTime(14, 0, 0); // 2:00 PM
        $endTime = Carbon::createFromTime(17, 0, 0);   // 5:00 PM

        // Check if an event already exists for this group with the same date
        $eventStartUtc = $eventDate->copy()->setTime($startTime->hour, $startTime->minute)->toDateTimeString();
        $existingEvent = DB::table('events')
            ->where('group', $groupId)
            ->where('event_start_utc', $eventStartUtc)
            ->first();
            
        if ($existingEvent) {
            $this->command->info('Event for this group on this date already exists. Skipping event creation.');
        } else {
            DB::table('events')->insert([
                'group' => $groupId,
                'event_start_utc' => $eventStartUtc,
                'event_end_utc' => $eventDate->copy()->setTime($endTime->hour, $endTime->minute)->toDateTimeString(),
                'venue' => 'Community Center',
                'location' => 'London, UK',
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'free_text' => 'Join us for our first Restart Party! Bring your broken electronics and learn how to fix them with our volunteers.',
                'approved' => 1,
                'timezone' => 'Europe/London',
                'shareable_code' => Str::random(8),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info('Event created successfully for group "Restart Project Demo Group".');
        }
    }
    
    /**
     * Create the second group with discourse data and its event
     */
    private function createSecondGroup()
    {
        // Check if the group already exists
        $existingGroup = DB::table('groups')->where('name', 'Restart Project Discourse Group')->first();
        
        if ($existingGroup) {
            $groupId = $existingGroup->idgroups;
            $this->command->info('Group "Restart Project Discourse Group" already exists. Using existing group.');
        } else {
            // Create second group with discourse data
            $groupId = DB::table('groups')->insertGetId([
                'name' => 'Restart Project Discourse Group',
                'location' => 'Manchester, UK',
                'latitude' => '53.4808',
                'longitude' => '-2.2426',
                'area' => 'Central Manchester',
                'free_text' => 'This is a demo group with discourse integration. We focus on community engagement and repair education.',
                'approved' => 1,
                'country_code' => 'GB',
                'postcode' => 'M1 1AD',
                'timezone' => 'Europe/London',
                'discourse_group' => 'restart-manchester',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info('Group "Restart Project Discourse Group" with discourse data created successfully.');
        }

        // Create an event for the second group
        // Event will be scheduled for 3 weeks from now
        $eventDate = Carbon::now()->addWeeks(3);
        $startTime = Carbon::createFromTime(13, 0, 0); // 1:00 PM
        $endTime = Carbon::createFromTime(16, 0, 0);   // 4:00 PM

        // Check if an event already exists for this group with the same date
        $eventStartUtc = $eventDate->copy()->setTime($startTime->hour, $startTime->minute)->toDateTimeString();
        $existingEvent = DB::table('events')
            ->where('group', $groupId)
            ->where('event_start_utc', $eventStartUtc)
            ->first();
            
        if ($existingEvent) {
            $this->command->info('Event for the discourse group on this date already exists. Skipping event creation.');
        } else {
            DB::table('events')->insert([
                'group' => $groupId,
                'event_start_utc' => $eventStartUtc,
                'event_end_utc' => $eventDate->copy()->setTime($endTime->hour, $endTime->minute)->toDateTimeString(),
                'venue' => 'Manchester Library',
                'location' => 'Manchester, UK',
                'latitude' => 53.4808,
                'longitude' => -2.2426,
                'free_text' => 'Join our Restart Party in Manchester! Bring your broken devices and learn repair skills.',
                'approved' => 1,
                'timezone' => 'Europe/London',
                'shareable_code' => Str::random(8),
                'discourse_thread' => '12345',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info('Event with discourse thread created successfully for group "Restart Project Discourse Group".');
        }
    }
} 