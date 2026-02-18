<?php

namespace Tests\Feature\Groups;

use App\Models\Device;
use App\Models\EventsUsers;
use App\Models\Group;
use App\Models\Party;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class GroupSoftDeleteTest extends TestCase
{
    public function testEventSoftDeleteRetainsDevices(): void
    {
        $this->loginAsTestUser(Role::ADMINISTRATOR);
        $groupId = $this->createGroup();
        $eventId = $this->createEvent($groupId, 'yesterday');
        $deviceId = $this->createDevice($eventId, 'misc');

        $deviceCountBefore = Device::where('event', $eventId)->count();
        $this->assertGreaterThan(0, $deviceCountBefore);

        // Delete the event via the web route
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);
        $response = $this->post("/party/delete/{$eventId}");

        // Event should be soft-deleted
        $this->assertSoftDeleted('events', ['idevents' => $eventId]);

        // Devices should still exist
        $deviceCountAfter = Device::where('event', $eventId)->count();
        $this->assertEquals($deviceCountBefore, $deviceCountAfter);

        // Event should be hidden from default queries
        $this->assertNull(Party::find($eventId));

        // But accessible with withTrashed
        $this->assertNotNull(Party::withTrashed()->find($eventId));
    }

    public function testGroupSoftDeleteCascadesToEvents(): void
    {
        $this->loginAsTestUser(Role::ADMINISTRATOR);
        $groupId = $this->createGroup();
        $eventId1 = $this->createEvent($groupId, 'yesterday');
        $eventId2 = $this->createEvent($groupId, 'tomorrow');
        $deviceId = $this->createDevice($eventId1, 'misc');

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        // Delete the group
        $response = $this->get("/group/delete/{$groupId}");
        $response->assertRedirect();

        // Group should be soft-deleted
        $this->assertSoftDeleted('groups', ['idgroups' => $groupId]);

        // All events should be soft-deleted
        $this->assertSoftDeleted('events', ['idevents' => $eventId1]);
        $this->assertSoftDeleted('events', ['idevents' => $eventId2]);

        // Devices should be unchanged
        $this->assertGreaterThan(0, Device::where('event', $eventId1)->count());
    }

    public function testAdminApiGroupSoftDelete(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $group = Group::factory()->create();
        $event = Party::factory()->create(['group' => $group->idgroups]);
        $deviceId = $this->createDevice($event->idevents, 'misc');

        // Soft-delete via admin API - should work even with devices
        $response = $this->post("/api/v2/admin/groups/{$group->idgroups}/delete");
        $response->assertJson(['success' => true]);

        // Group should be soft-deleted
        $this->assertSoftDeleted('groups', ['idgroups' => $group->idgroups]);

        // Event should be soft-deleted
        $this->assertSoftDeleted('events', ['idevents' => $event->idevents]);

        // Devices should still exist
        $this->assertGreaterThan(0, Device::where('event', $event->idevents)->count());
    }

    public function testAdminApiGroupRestore(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $group = Group::factory()->create();
        $event1 = Party::factory()->create(['group' => $group->idgroups]);
        $event2 = Party::factory()->create(['group' => $group->idgroups]);

        // Soft-delete the group
        $this->post("/api/v2/admin/groups/{$group->idgroups}/delete");
        $this->assertSoftDeleted('groups', ['idgroups' => $group->idgroups]);

        // Restore the group
        $response = $this->post("/api/v2/admin/groups/{$group->idgroups}/restore");
        $response->assertJson(['success' => true]);

        // Group should be restored
        $group->refresh();
        $this->assertNull($group->deleted_at);

        // Events should be restored
        $event1->refresh();
        $event2->refresh();
        $this->assertNull($event1->deleted_at);
        $this->assertNull($event2->deleted_at);
    }

    public function testAdminApiEventRestoreWithDeletedGroupReturns409(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $group = Group::factory()->create();
        $event = Party::factory()->create(['group' => $group->idgroups]);

        // Soft-delete the group (cascades to events)
        $this->post("/api/v2/admin/groups/{$group->idgroups}/delete");

        // Try to restore the event while group is deleted
        $response = $this->post("/api/v2/admin/events/{$event->idevents}/restore");
        $response->assertStatus(409);
        $response->assertJson(['success' => false]);
    }

    public function testAdminApiEventRestoreAfterGroupRestore(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $group = Group::factory()->create();
        $event = Party::factory()->create(['group' => $group->idgroups]);

        // Soft-delete the group (cascades to events)
        $this->post("/api/v2/admin/groups/{$group->idgroups}/delete");

        // Restore the group first
        $this->post("/api/v2/admin/groups/{$group->idgroups}/restore");

        // Now event should already be restored (group restore cascades)
        $event->refresh();
        $this->assertNull($event->deleted_at);
    }

    public function testDeletedGroupsHiddenFromDefaultQueries(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $group = Group::factory()->create();
        $groupId = $group->idgroups;

        // Visible before deletion
        $this->assertNotNull(Group::find($groupId));

        // Soft-delete
        $this->post("/api/v2/admin/groups/{$groupId}/delete");

        // Hidden from default queries
        $this->assertNull(Group::find($groupId));

        // Visible with withTrashed
        $this->assertNotNull(Group::withTrashed()->find($groupId));
    }

    public function testAdminApiDeletedFilter(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $activeGroup = Group::factory()->create(['name' => 'Active Group']);
        $deletedGroup = Group::factory()->create(['name' => 'Deleted Group']);

        // Soft-delete one group
        $this->post("/api/v2/admin/groups/{$deletedGroup->idgroups}/delete");

        // Default (active) - should only show active group
        $response = $this->get('/api/v2/admin/groups');
        $response->assertJson(['success' => true]);
        $data = $response->json('data');
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('Active Group', $names);
        $this->assertNotContains('Deleted Group', $names);

        // Only deleted - should only show deleted group
        $response = $this->get('/api/v2/admin/groups?deleted=only');
        $data = $response->json('data');
        $names = collect($data)->pluck('name')->toArray();
        $this->assertNotContains('Active Group', $names);
        $this->assertContains('Deleted Group', $names);

        // All - should show both
        $response = $this->get('/api/v2/admin/groups?deleted=all');
        $data = $response->json('data');
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('Active Group', $names);
        $this->assertContains('Deleted Group', $names);
    }
}
