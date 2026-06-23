<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Role;
use App\Models\Skills;
use App\Models\User;
use App\Models\UserGroups;
use App\Models\UsersSkills;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivilegeEscalationTest extends TestCase
{
    // NB: This suite deliberately does NOT use RefreshDatabase. The base TestCase already
    // wraps each test in its own DB transaction (setUp beginTransaction / tearDown rollBack).
    // Adding RefreshDatabase nests a second transaction, and a legacy-table write inside a
    // test implicitly commits and destroys the savepoint, breaking tearDown's rollback.
    // ProfileTest follows the same (RefreshDatabase-free) convention.

    /**
     * C1: Restarter cannot edit another user's profile info.
     */
    public function test_restarter_cannot_edit_another_users_info(): void
    {
        $attacker = User::factory()->restarter()->create();
        $victim = User::factory()->restarter()->create();

        $this->actingAs($attacker);
        $this->withExceptionHandling();

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
        $this->withExceptionHandling();

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
        $this->withExceptionHandling();

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
        $this->withExceptionHandling();

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

    // -------------------------------------------------------------------------
    // F001: Any user can soft-delete any other user via POST /user/soft-delete
    // -------------------------------------------------------------------------

    #[Test]
    public function restarter_cannot_soft_delete_another_user(): void
    {
        $this->withExceptionHandling();

        $attacker = User::factory()->restarter()->create();
        $victim   = User::factory()->restarter()->create();

        $this->actingAs($attacker);

        $response = $this->post('/user/soft-delete', ['id' => $victim->id]);

        $response->assertStatus(403);
        // Victim must NOT be soft-deleted.
        $this->assertFalse(User::withTrashed()->find($victim->id)->trashed());
    }

    #[Test]
    public function admin_can_soft_delete_another_user(): void
    {
        $admin  = User::factory()->administrator()->create();
        $victim = User::factory()->restarter()->create();

        $this->actingAs($admin);

        $response = $this->post('/user/soft-delete', ['id' => $victim->id]);

        $response->assertRedirect('user/all');
        // Victim is soft-deleted.
        $this->assertTrue(User::withTrashed()->find($victim->id)->trashed());
    }

    #[Test]
    public function user_can_soft_delete_themselves(): void
    {
        $user = User::factory()->restarter()->create();

        $this->actingAs($user);

        $response = $this->post('/user/soft-delete', ['id' => $user->id]);

        $response->assertRedirect('login');
        $this->assertTrue(User::withTrashed()->find($user->id)->trashed());
    }

    // -------------------------------------------------------------------------
    // F002: A Host can edit/reset the password of any unrelated user via edit()
    // -------------------------------------------------------------------------

    #[Test]
    public function host_cannot_reset_unrelated_users_password_via_edit_endpoint(): void
    {
        $this->withExceptionHandling();

        $host   = User::factory()->host()->create();
        $victim = User::factory()->restarter()->create(['password' => bcrypt('originalPassword')]);

        $this->actingAs($host);

        $response = $this->post('/user/edit/' . $victim->id, [
            'name'             => $victim->name,
            'email'            => $victim->email,
            'groups'           => [],
            'new-password'     => 'hackedPassword',
            'password-confirm' => 'hackedPassword',
        ]);

        $response->assertStatus(403);
        $this->assertTrue(Hash::check('originalPassword', $victim->fresh()->password));
    }

    #[Test]
    public function host_cannot_edit_unrelated_users_profile_via_edit_endpoint(): void
    {
        $this->withExceptionHandling();

        $host   = User::factory()->host()->create();
        $victim = User::factory()->restarter()->create(['name' => 'Original Name']);

        $this->actingAs($host);

        $response = $this->post('/user/edit/' . $victim->id, [
            'name'   => 'Hacked Name',
            'email'  => $victim->email,
            'groups' => [],
        ]);

        $response->assertStatus(403);
        $this->assertEquals('Original Name', $victim->fresh()->name);
    }

    #[Test]
    public function admin_can_reset_another_users_password_via_edit_endpoint(): void
    {
        $GLOBALS['_FILES'] = [];

        $admin  = User::factory()->administrator()->create();
        $target = User::factory()->restarter()->create(['password' => bcrypt('originalPassword')]);

        $this->actingAs($admin);

        $response = $this->post('/user/edit/' . $target->id, [
            'name'             => $target->name,
            'email'            => $target->email,
            'groups'           => [],
            'new-password'     => 'newPassword123',
            'password-confirm' => 'newPassword123',
        ]);

        $this->assertFalse($response->isClientError());
        $this->assertTrue(Hash::check('newPassword123', $target->fresh()->password));
    }

    #[Test]
    public function user_can_reset_own_password_via_edit_endpoint(): void
    {
        $GLOBALS['_FILES'] = [];

        $user = User::factory()->restarter()->create(['password' => bcrypt('originalPassword')]);

        $this->actingAs($user);

        $response = $this->post('/user/edit/' . $user->id, [
            'name'             => $user->name,
            'email'            => $user->email,
            'groups'           => [],
            'new-password'     => 'newPassword123',
            'password-confirm' => 'newPassword123',
        ]);

        $this->assertFalse($response->isClientError());
        $this->assertTrue(Hash::check('newPassword123', $user->fresh()->password));
    }

    // -------------------------------------------------------------------------
    // F004: Any user can overwrite any other user's skills via edit-tags
    // -------------------------------------------------------------------------

    #[Test]
    public function restarter_cannot_edit_another_users_tags(): void
    {
        $this->withExceptionHandling();

        $attacker = User::factory()->restarter()->create();
        $victim   = User::factory()->restarter()->create();

        $this->actingAs($attacker);

        $response = $this->post('/profile/edit-tags', [
            'id'   => $victim->id,
            'tags' => [1],
        ]);

        $response->assertStatus(403);
        $this->assertEmpty(UsersSkills::where('user', $victim->id)->get());
    }

    #[Test]
    public function user_can_edit_own_tags_and_self_promote_to_host(): void
    {
        $user = User::factory()->restarter()->create();

        // A category-1 ("organising") skill qualifies the user for the Host role by design.
        $skill = Skills::create([
            'skill_name'  => 'UT Host Skill',
            'category'    => 1,
            'description' => 'Organising',
        ]);

        $this->actingAs($user);

        $response = $this->post('/profile/edit-tags', [
            'id'   => $user->id,
            'tags' => [$skill->id],
        ]);

        $this->assertTrue($response->isRedirection());
        $this->assertEquals(Role::HOST, $user->fresh()->role);
    }

    // -------------------------------------------------------------------------
    // F006: Any user can change any other user's language via edit-language
    // -------------------------------------------------------------------------

    #[Test]
    public function restarter_cannot_change_another_users_language(): void
    {
        $this->withExceptionHandling();

        $attacker = User::factory()->restarter()->create();
        $victim   = User::factory()->restarter()->create(['language' => 'en']);

        $this->actingAs($attacker);

        $response = $this->post('/profile/edit-language', [
            'id'            => $victim->id,
            'user_language' => 'fr',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('en', $victim->fresh()->language);
    }

    #[Test]
    public function user_can_change_own_language(): void
    {
        $user = User::factory()->restarter()->create(['language' => 'en']);

        $this->actingAs($user);

        $response = $this->post('/profile/edit-language', [
            'id'            => $user->id,
            'user_language' => 'fr',
        ]);

        $this->assertTrue($response->isRedirection());
        $this->assertEquals('fr', $user->fresh()->language);
    }

    #[Test]
    public function admin_can_change_another_users_language(): void
    {
        $admin  = User::factory()->administrator()->create();
        $victim = User::factory()->restarter()->create(['language' => 'en']);

        $this->actingAs($admin);

        $response = $this->post('/profile/edit-language', [
            'id'            => $victim->id,
            'user_language' => 'fr',
        ]);

        $this->assertTrue($response->isRedirection());
        $this->assertEquals('fr', $victim->fresh()->language);
    }

    // -------------------------------------------------------------------------
    // F007: Any user can toggle any other user's invites preference
    // -------------------------------------------------------------------------

    #[Test]
    public function restarter_cannot_toggle_another_users_invites(): void
    {
        $this->withExceptionHandling();

        $attacker = User::factory()->restarter()->create();
        $victim   = User::factory()->restarter()->create(['invites' => 1]);

        $this->actingAs($attacker);

        // Omitting 'invites' attempts to set it to 0.
        $response = $this->post('/profile/edit-preferences', ['id' => $victim->id]);

        $response->assertStatus(403);
        $this->assertEquals(1, $victim->fresh()->invites);
    }

    #[Test]
    public function user_can_toggle_own_invites(): void
    {
        $user = User::factory()->restarter()->create(['invites' => 1]);

        $this->actingAs($user);

        $response = $this->post('/profile/edit-preferences', ['id' => $user->id]);

        $this->assertTrue($response->isRedirection());
        $this->assertEquals(0, $user->fresh()->invites);
    }

    // -------------------------------------------------------------------------
    // edit(): group membership is an admin-only field. A self-editing non-admin
    // must not be able to (re)assign their own groups, and an edit() POST that
    // omits the 'groups' field must not wipe existing memberships.
    // -------------------------------------------------------------------------

    #[Test]
    public function restarter_cannot_self_assign_group_membership_via_edit_endpoint(): void
    {
        $GLOBALS['_FILES'] = [];

        $user  = User::factory()->restarter()->create();
        $group = Group::factory()->create();

        $this->actingAs($user);

        $this->post('/user/edit/' . $user->id, [
            'name'   => $user->name,
            'email'  => $user->email,
            'groups' => [$group->idgroups],
        ]);

        // The admin-only group field must be ignored for a non-admin self-edit.
        $this->assertDatabaseMissing('users_groups', [
            'user'  => $user->id,
            'group' => $group->idgroups,
        ]);
    }

    #[Test]
    public function edit_endpoint_preserves_group_memberships_when_groups_field_absent(): void
    {
        $GLOBALS['_FILES'] = [];

        $admin  = User::factory()->administrator()->create();
        $member = User::factory()->restarter()->create();
        $group  = Group::factory()->create();
        UserGroups::create(['user' => $member->id, 'group' => $group->idgroups]);

        $this->actingAs($admin);

        // POST without a 'groups' field must NOT wipe the target's existing memberships.
        $this->post('/user/edit/' . $member->id, [
            'name'  => $member->name,
            'email' => $member->email,
        ]);

        $this->assertDatabaseHas('users_groups', [
            'user'       => $member->id,
            'group'      => $group->idgroups,
            'deleted_at' => null,
        ]);
    }
}
