<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Group;
use App\Models\Network;
use App\Models\Party;
use App\Models\Role;
use Carbon\Carbon;
use Tests\TestCase;

class PublicEventsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['restarters.features.public_events_api' => true]);
    }

    public function test_public_events_api_requires_bearer_key(): void
    {
        $response = $this->get('/api/public/v2/events');
        $response->assertStatus(401);
    }

    public function test_public_events_api_ignores_query_token_auth(): void
    {
        $response = $this->get('/api/public/v2/events?api_token=not_a_valid_public_key');
        $response->assertStatus(401);
    }

    public function test_public_events_api_lists_only_upcoming_approved_events_by_default(): void
    {
        $admin = $this->createUserWithToken(Role::ADMINISTRATOR);
        $this->actingAs($admin);

        $approvedGroupId = $this->createGroup('Public API Group', 'https://example.com', 'London', 'Some text', true, true);
        $futureApprovedId = $this->createEvent($approvedGroupId, 'tomorrow', true, true);
        $pastApprovedId = $this->createEvent($approvedGroupId, 'yesterday', true, true);
        $futureUnapprovedId = $this->createEvent($approvedGroupId, 'next week', true, false);

        $unapprovedGroupId = $this->createGroup('Hidden Group', 'https://example.com', 'London', 'Some text', true, false);
        $hiddenEventId = $this->createEvent($unapprovedGroupId, 'next week', true, true);

        $token = $this->createPublicApiToken();
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->get('/api/public/v2/events');
        $response->assertSuccessful();

        $json = json_decode($response->getContent(), true);
        $ids = array_column($json['data'], 'id');

        $this->assertContains($futureApprovedId, $ids);
        $this->assertNotContains($pastApprovedId, $ids);
        $this->assertNotContains($futureUnapprovedId, $ids);
        $this->assertNotContains($hiddenEventId, $ids);

        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('sync', $json);
        $this->assertArrayHasKey('generated_at', $json['sync']);
        $this->assertArrayHasKey('max_updated_at', $json['sync']);
        $this->assertArrayHasKey('description', $json['data'][0]);
        $this->assertArrayNotHasKey('stats', $json['data'][0]);
        $this->assertArrayNotHasKey('network_data', $json['data'][0]);
        $this->assertArrayNotHasKey('networks', $json['data'][0]['group']);
    }

    public function test_public_events_api_supports_group_filters(): void
    {
        $admin = $this->createUserWithToken(Role::ADMINISTRATOR);
        $this->actingAs($admin);

        $group1Id = $this->createGroup('Group One', 'https://example.com', 'London', 'Some text', true, true);
        $group2Id = $this->createGroup('Group Two', 'https://example.com', 'London', 'Some text', true, true);

        $event1 = $this->createEvent($group1Id, 'tomorrow', true, true);
        $event2 = $this->createEvent($group2Id, 'tomorrow', true, true);

        $token = $this->createPublicApiToken();

        $groupResponse = $this->withHeader('Authorization', 'Bearer ' . $token)->get("/api/public/v2/groups/{$group1Id}/events");
        $groupResponse->assertSuccessful();
        $groupJson = json_decode($groupResponse->getContent(), true);
        $groupIds = array_column($groupJson['data'], 'id');
        $this->assertContains($event1, $groupIds);
        $this->assertNotContains($event2, $groupIds);
    }

    public function test_public_events_api_respects_allowed_network_restrictions(): void
    {
        $admin = $this->createUserWithToken(Role::ADMINISTRATOR);
        $this->actingAs($admin);

        $group1Id = $this->createGroup('Restricted Group One', 'https://example.com', 'London', 'Some text', true, true);
        $group2Id = $this->createGroup('Restricted Group Two', 'https://example.com', 'London', 'Some text', true, true);

        $event1 = $this->createEvent($group1Id, 'tomorrow', true, true);
        $event2 = $this->createEvent($group2Id, 'tomorrow', true, true);

        $allowedNetwork = Network::factory()->create();
        $blockedNetwork = Network::factory()->create();
        $allowedNetwork->addGroup(Group::findOrFail($group1Id));
        $blockedNetwork->addGroup(Group::findOrFail($group2Id));

        $token = $this->createPublicApiToken([
            'allowed_network_ids' => [$allowedNetwork->id],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->get('/api/public/v2/events');
        $response->assertSuccessful();
        $json = json_decode($response->getContent(), true);
        $ids = array_column($json['data'], 'id');

        $this->assertContains($event1, $ids);
        $this->assertNotContains($event2, $ids);
    }

    public function test_public_events_api_enforces_allowed_origins_when_configured(): void
    {
        $admin = $this->createUserWithToken(Role::ADMINISTRATOR);
        $this->actingAs($admin);
        $groupId = $this->createGroup('Origin Test Group', 'https://example.com', 'London', 'Some text', true, true);
        $this->createEvent($groupId, 'tomorrow', true, true);

        $token = $this->createPublicApiToken([
            'allowed_origins' => ['https://allowed.example'],
        ]);

        $forbidden = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Origin' => 'https://disallowed.example',
        ])->get('/api/public/v2/events');
        $forbidden->assertStatus(403);

        $allowed = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Origin' => 'https://allowed.example',
        ])->get('/api/public/v2/events');
        $allowed->assertSuccessful();
    }

    public function test_public_events_api_show_event_returns_only_public_approved_events(): void
    {
        $this->withExceptionHandling();

        $admin = $this->createUserWithToken(Role::ADMINISTRATOR);
        $this->actingAs($admin);

        $groupId = $this->createGroup('Show Event Group', 'https://example.com', 'London', 'Some text', true, true);
        $approvedEventId = $this->createEvent($groupId, 'tomorrow', true, true);
        $unapprovedEventId = $this->createEvent($groupId, 'next week', true, false);

        $token = $this->createPublicApiToken();

        $visible = $this->withHeader('Authorization', 'Bearer ' . $token)->get("/api/public/v2/events/{$approvedEventId}");
        $visible->assertSuccessful();
        $json = json_decode($visible->getContent(), true);
        $this->assertEquals($approvedEventId, $json['data']['id']);
        $this->assertArrayNotHasKey('stats', $json['data']);
        $this->assertArrayNotHasKey('network_data', $json['data']);
        $this->assertArrayNotHasKey('networks', $json['data']['group']);

        $hidden = $this->withHeader('Authorization', 'Bearer ' . $token)->get("/api/public/v2/events/{$unapprovedEventId}");
        $hidden->assertStatus(404);
    }

    public function test_public_events_api_supports_updated_window_filters(): void
    {
        $admin = $this->createUserWithToken(Role::ADMINISTRATOR);
        $this->actingAs($admin);

        $groupId = $this->createGroup('Updated Window Group', 'https://example.com', 'London', 'Some text', true, true);
        $eventId = $this->createEvent($groupId, 'tomorrow', true, true);

        $event = Party::findOrFail($eventId);
        $event->timestamps = false;
        $event->updated_at = Carbon::parse('2000-01-01 00:00:00')->toDateTimeString();
        $event->save();

        $token = $this->createPublicApiToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->get(
            '/api/public/v2/events?updated_start=' . urlencode(Carbon::parse('2010-01-01')->toIso8601String())
        );
        $response->assertSuccessful();
        $json = json_decode($response->getContent(), true);
        $this->assertEquals([], $json['data']);
    }

    private function createPublicApiToken(array $attributes = []): string
    {
        $token = 'public_api_token_' . uniqid();

        ApiClient::factory()->create(array_merge([
            'token_hash' => hash('sha256', $token),
            'scopes' => ['events:read'],
            'active' => true,
            'expires_at' => null,
        ], $attributes));

        return $token;
    }
}
