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

        // The group must be approved, otherwise events on it are not visible to
        // anonymous visitors (PartyController::view aborts with 404) and the
        // public-page leak assertions below would never execute.
        $group = Group::factory()->create(['approved' => true]);
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
            'username' => 'SENSITIVE_USERNAME',
            'external_user_id' => 'SENSITIVE_EXT_ID',
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

    /**
     * Assert that every embedded "volunteer" object is limited to the
     * {id, name, email} allowlist. The full User model used to leak here
     * because a loaded Eloquent relation overrides the allowlist array
     * during serialization, so checking only the $hidden fields above is
     * not enough — username, external ids, location, age, consent dates,
     * etc. would still be exposed.
     */
    private function assertVolunteerObjectsAllowlistOnly(string $content): void
    {
        // Props are json_encode()d then HTML-escaped by Blade ({{ }}), so
        // decode entities before extracting the embedded JSON.
        $decoded = html_entity_decode($content, ENT_QUOTES);

        $this->assertMatchesRegularExpression(
            '/"volunteer":\{/',
            $decoded,
            'No volunteer object was rendered; the allowlist assertions would be vacuous'
        );

        preg_match_all('/"volunteer":(\{[^}]*\})/', $decoded, $matches);

        foreach ($matches[1] as $json) {
            $object = json_decode($json, true);
            $this->assertIsArray($object, "Could not decode volunteer object: $json");

            $keys = array_keys($object);
            sort($keys);

            $this->assertSame(
                ['email', 'id', 'name'],
                $keys,
                'Volunteer object exposes more than the {id, name, email} allowlist: ' . $json
            );
        }

        // Sentinel PII values that must never reach any serialized response.
        $this->assertStringNotContainsString('SENSITIVE_USERNAME', $content);
        $this->assertStringNotContainsString('SENSITIVE_EXT_ID', $content);
    }

    public function testPublicEventPageDoesNotLeakSensitiveUserFields(): void
    {
        [$event, $volunteer] = $this->createEventWithVolunteer();

        $response = $this->get('/party/view/' . $event->idevents);

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertNoSensitiveFields($content);
        $this->assertVolunteerObjectsAllowlistOnly($content);

        // Anonymous viewers must not see volunteer email addresses.
        $this->assertStringNotContainsString(
            $volunteer->email,
            html_entity_decode($content, ENT_QUOTES),
            'Volunteer email address exposed to an anonymous viewer'
        );
    }

    public function testVolunteersApiDoesNotLeakSensitiveUserFields(): void
    {
        [$event] = $this->createEventWithVolunteer();

        $admin = $this->createUserWithToken(Role::ADMINISTRATOR);
        $this->actingAs($admin);

        $response = $this->get('/api/events/' . $event->idevents . '/volunteers');

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertNoSensitiveFields($content);
        $this->assertVolunteerObjectsAllowlistOnly($content);
    }
}
