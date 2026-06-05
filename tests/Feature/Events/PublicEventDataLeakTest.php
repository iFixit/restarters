<?php

namespace Tests\Feature;

use App\Models\EventsUsers;
use App\Models\Group;
use App\Models\Network;
use App\Models\Party;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublicEventDataLeakTest extends TestCase
{
    private array $sensitiveFields = [
        'api_token',
        'calendar_hash',
        'recovery',
        'recovery_expires',
        'latitude',
        'longitude',
    ];

    private function createEventWithVolunteer(array $userOverrides = []): array
    {
        Queue::fake();

        $group = Group::factory()->create();
        $network = Network::factory()->create();
        $network->addGroup($group);

        $event = Party::factory()->create([
            'group' => $group,
            'event_start_utc' => '2130-01-01T12:13:00+00:00',
            'event_end_utc' => '2130-01-01T13:14:00+00:00',
        ]);

        $volunteer = User::factory()->create(array_merge([
            'api_token' => 'SENSITIVE_TOKEN_VALUE',
            'calendar_hash' => 'SENSITIVE_CAL_HASH',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'recovery' => 'SENSITIVE_RECOVERY_TOKEN',
            'recovery_expires' => now()->addHour(),
        ], $userOverrides));

        EventsUsers::create([
            'event' => $event->idevents,
            'user' => $volunteer->id,
            'status' => 1,
            'role' => Role::RESTARTER,
        ]);

        return [$event, $volunteer];
    }

    private function assertNoSensitiveFields(string $content): void
    {
        foreach ($this->sensitiveFields as $field) {
            $this->assertStringNotContainsString(
                '"' . $field . '"',
                $content,
                "Sensitive field '$field' found in response"
            );
        }

        $this->assertStringNotContainsString('SENSITIVE_TOKEN_VALUE', $content);
        $this->assertStringNotContainsString('SENSITIVE_CAL_HASH', $content);
        $this->assertStringNotContainsString('SENSITIVE_RECOVERY_TOKEN', $content);
    }

    public function testPublicEventPageDoesNotLeakSensitiveUserFields(): void
    {
        [$event] = $this->createEventWithVolunteer();

        $response = $this->get('/party/view/' . $event->idevents);

        $response->assertStatus(200);
        $this->assertNoSensitiveFields($response->getContent());
    }

    public function testVolunteersApiDoesNotLeakSensitiveUserFields(): void
    {
        [$event] = $this->createEventWithVolunteer();

        $admin = $this->createUserWithToken(Role::ADMINISTRATOR);
        $this->actingAs($admin);

        $response = $this->get('/api/events/' . $event->idevents . '/volunteers');

        $response->assertStatus(200);
        $this->assertNoSensitiveFields($response->getContent());
    }
}
