<?php

namespace Tests\Unit;

use App\Models\Role;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // No need for truncation anymore as we're using transactions in parent::setUp()
    }

    #[Test]
    public function user_can_have_network_coordinator_role(): void
    {
        // arrange
        $user = User::factory()->create();

        $user->role = Role::NETWORK_COORDINATOR;
        $user->save();

        // assert
        $this->assertTrue($user->hasRole('NetworkCoordinator'));
    }

    #[Test]
    public function can_change_restarter_to_host_role(): void
    {
        // arrange
        $user = User::factory()->create();
        $user->role = Role::RESTARTER;

        // act
        $user->convertToHost();

        // assert
        $this->assertTrue($user->hasRole('Host'));
    }

    #[Test]
    public function cannot_change_admin_or_coordinator_to_host_role(): void
    {
        // arrange
        $user1 = User::factory()->administrator()->create();
        $user2 = User::factory()->networkCoordinator()->create();

        // act
        $user1->convertToHost();
        $user2->convertToHost();

        // assert
        $this->assertTrue($user1->hasRole('Administrator'));
        $this->assertTrue($user2->hasRole('NetworkCoordinator'));
    }
}
