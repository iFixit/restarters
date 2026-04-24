<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * C1: Restarter cannot edit another user's profile info.
     */
    public function test_restarter_cannot_edit_another_users_info(): void
    {
        $attacker = User::factory()->restarter()->create();
        $victim = User::factory()->restarter()->create();

        $this->actingAs($attacker);

        $response = $this->post('/profile/edit-info', [
            'id' => $victim->id,
            'email' => $victim->email,
            'name' => 'Hacked Name',
            'country' => $victim->country_code,
            'townCity' => $victim->location,
            'age' => $victim->age,
            'gender' => $victim->gender,
            'biography' => 'hacked',
        ]);

        $response->assertStatus(403);
        $this->assertNotEquals('Hacked Name', $victim->fresh()->name);
    }

    /**
     * C1: Restarter cannot change another user's password.
     */
    public function test_restarter_cannot_change_another_users_password(): void
    {
        $attacker = User::factory()->restarter()->create();
        $victim = User::factory()->restarter()->create();

        $this->actingAs($attacker);

        $response = $this->post('/profile/edit-password', [
            'id' => $victim->id,
            'current-password' => 'secret',
            'new-password' => 'hacked123',
            'new-password-repeat' => 'hacked123',
        ]);

        $response->assertStatus(403);
    }

    /**
     * C1: Restarter cannot change another user's photo.
     */
    public function test_restarter_cannot_change_another_users_photo(): void
    {
        $attacker = User::factory()->restarter()->create();
        $victim = User::factory()->restarter()->create();

        $this->actingAs($attacker);

        $response = $this->post('/profile/edit-photo', [
            'id' => $victim->id,
        ]);

        $response->assertStatus(403);
    }

    /**
     * C1: Restarter cannot access admin edit settings.
     */
    public function test_restarter_cannot_access_admin_edit_settings(): void
    {
        $attacker = User::factory()->restarter()->create();

        $this->actingAs($attacker);

        $response = $this->post('/profile/edit-admin-settings', [
            'id' => $attacker->id,
            'user_role' => Role::ROOT,
        ]);

        $response->assertStatus(403);
        $this->assertEquals(Role::RESTARTER, $attacker->fresh()->role);
    }

    /**
     * C1 positive: Admin CAN change role via admin edit settings.
     */
    public function test_admin_can_change_role_via_admin_edit(): void
    {
        $admin = User::factory()->administrator()->create();
        $user = User::factory()->restarter()->create();

        $this->actingAs($admin);

        $response = $this->post('/profile/edit-admin-settings', [
            'id' => $user->id,
            'user_role' => Role::HOST,
            'assigned_groups' => [],
            'preferences' => [],
            'permissions' => [],
        ]);

        $response->assertSessionHas('message');
        $this->assertEquals(Role::HOST, $user->fresh()->role);
    }

    /**
     * C1 positive: Admin CAN edit another user's info.
     */
    public function test_admin_can_edit_another_users_info(): void
    {
        $admin = User::factory()->administrator()->create();
        $user = User::factory()->restarter()->create();

        $this->actingAs($admin);

        $response = $this->post('/profile/edit-info', [
            'id' => $user->id,
            'email' => $user->email,
            'name' => 'Updated By Admin',
            'country' => $user->country_code,
            'townCity' => $user->location,
            'age' => $user->age,
            'gender' => $user->gender,
            'biography' => $user->biography,
        ]);

        $response->assertRedirect();
        $this->assertEquals('Updated By Admin', $user->fresh()->name);
    }

    /**
     * C2/M1: User cannot escalate role via /user/edit endpoint.
     */
    public function test_user_cannot_escalate_role_via_edit_endpoint(): void
    {
        $admin = User::factory()->administrator()->create();
        $user = User::factory()->restarter()->create();

        $this->actingAs($admin);

        $response = $this->post("/user/edit/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => Role::ROOT,
        ]);

        $this->assertNotEquals(Role::ROOT, $user->fresh()->role);
    }

    /**
     * C2/M1: User cannot overwrite api_token via /user/edit endpoint.
     */
    public function test_user_cannot_overwrite_api_token_via_edit_endpoint(): void
    {
        $admin = User::factory()->administrator()->create();
        $user = User::factory()->restarter()->create();
        $originalToken = $user->api_token;

        $this->actingAs($admin);

        $response = $this->post("/user/edit/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'api_token' => 'stolen_token_value',
        ]);

        $this->assertEquals($originalToken, $user->fresh()->api_token);
    }
}
