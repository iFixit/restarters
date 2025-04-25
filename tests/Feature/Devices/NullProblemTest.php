<?php

namespace Tests\Feature\Devices;

use App\Models\Device;
use PHPUnit\Framework\Attributes\Test;
use App\Models\Party;
use App\Models\User;
use DB;
use Tests\TestCase;
use App\Models\Role;

class NullProblemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // No need for explicit truncation as we're using transactions in the parent
    }

    #[Test]
    public function test_invalid_problem_field(): void
    {
        $admin = $this->loginAsTestUser(Role::ADMINISTRATOR);
        $group = $this->createGroup('Whatever');

        $date = '2022-01-01';
        $event = $this->createEvent($group, $date);

        $dev_id = $this->createDevice($event, 'Fixed', NULL);

        $dev = Device::find($dev_id);
        // NULL problem is a special case, handled.
        $this->assertEquals($dev->problem, null);
        $this->assertNotEquals($dev->problem, '');

        // We can request it.
        $response = $this->get('/api/v2/devices/' . $dev_id);
        $response->assertSuccessful();
        $json = json_decode($response->getContent(), true);
        $this->assertNull($json['data']['problem']);

        // Now modify it.
        $response = $this->patch('/api/v2/devices/' . $dev_id, [
            'problem' => 'New problem',
        ]);
        $response->assertSuccessful();

        $dev = Device::find($dev_id);
        $this->assertEquals($dev->problem, 'New problem');
    }
}
