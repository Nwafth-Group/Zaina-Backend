<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Admin management of end-user accounts (`/api/v1/admin/users`). Unlike the
 * employees area, these routes are gated per-action by Spatie permissions:
 * index/show require `users.view`; update/toggle-status/reset-password require
 * `users.edit`. There is NO Super-Admin gate bypass, so a super_admin by type
 * is still forbidden without the explicit permission — the behavior these
 * tests pin down. `verify` sits behind `IsAdmin` only (no permission).
 *
 * Permissions/roles are created on the `sanctum` guard to match the User model.
 */
class UserManagementTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/users';

    /**
     * Authenticate as a super_admin that holds the given permissions.
     *
     * @param  array<int, string>  $permissions
     * @param  array<string, mixed>  $overrides
     */
    private function actingAsAdminWith(array $permissions = [], array $overrides = []): User
    {
        $admin = User::factory()->superAdmin()->create($overrides);

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }
        if ($permissions !== []) {
            $admin->givePermissionTo($permissions);
        }
        // Permissions are resolved through a cached registrar; flush so the
        // freshly granted permissions are visible to the request.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($admin, 'sanctum');

        return $admin;
    }

    // ---------------------------------------------------------------------
    // Authorization & permission gating
    // ---------------------------------------------------------------------

    public function test_guest_cannot_list_users(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_customer_cannot_list_users(): void
    {
        $this->actingAsCustomer();

        $this->getJson(self::ENDPOINT)->assertStatus(403);
    }

    public function test_admin_without_users_view_permission_is_forbidden(): void
    {
        // super_admin by type, but no `users.view` permission -> 403.
        $this->actingAsAdmin();

        $this->getJson(self::ENDPOINT)->assertStatus(403);
    }

    public function test_admin_with_users_view_can_list(): void
    {
        $this->actingAsAdminWith(['users.view']);
        User::factory()->customer()->count(2)->create();

        // Filter by type to isolate the seeded customers from the acting admin.
        $this->getJson(self::ENDPOINT.'?type=customer')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_admin_without_users_view_cannot_show_user(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->customer()->create();

        $this->getJson(self::ENDPOINT.'/'.$user->id)->assertStatus(403);
    }

    public function test_admin_with_users_view_can_show_user(): void
    {
        $this->actingAsAdminWith(['users.view']);
        $user = User::factory()->customer()->create();

        $this->getJson(self::ENDPOINT.'/'.$user->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $user->id);
    }

    // ---------------------------------------------------------------------
    // Index filters (require users.view)
    // ---------------------------------------------------------------------

    public function test_admin_can_filter_users_by_status(): void
    {
        $this->actingAsAdminWith(['users.view']);
        User::factory()->customer()->create(['status' => 'active', 'first_name' => 'Acto']);
        User::factory()->customer()->create(['status' => 'inactive', 'first_name' => 'Inacto']);

        $this->getJson(self::ENDPOINT.'?type=customer&status=inactive')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.first_name', 'Inacto');
    }

    public function test_admin_can_filter_users_by_multiple_types(): void
    {
        $this->actingAsAdminWith(['users.view']);
        User::factory()->customer()->create(['first_name' => 'Custo']);
        User::factory()->operation()->create(['first_name' => 'Opero']);

        $response = $this->getJson(self::ENDPOINT.'?types=operation,super_admin')
            ->assertStatus(200);

        $names = collect($response->json('data.items'))->pluck('first_name')->all();
        $types = collect($response->json('data.items'))->pluck('type')->unique()->all();

        // The "All" admin tab lists only admin account types — customers are excluded.
        $this->assertContains('Opero', $names);
        $this->assertNotContains('Custo', $names);
        $this->assertNotContains('customer', $types);
    }

    public function test_admin_can_search_users_by_email(): void
    {
        $this->actingAsAdminWith(['users.view']);
        User::factory()->customer()->create(['email' => 'findme@zaina.test', 'first_name' => 'Find']);
        User::factory()->customer()->create(['email' => 'other@zaina.test']);

        $this->getJson(self::ENDPOINT.'?search=findme@zaina.test')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.first_name', 'Find');
    }

    // ---------------------------------------------------------------------
    // Verify (IsAdmin only — no users.* permission)
    // ---------------------------------------------------------------------

    public function test_admin_can_approve_user_verification(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->customer()->create(['verification_status' => 'pending']);

        $this->postJson(self::ENDPOINT.'/'.$user->id.'/verify', ['action' => 'approve'])
            ->assertStatus(200);

        $this->assertSame('approved', $user->fresh()->verification_status);
    }

    public function test_admin_can_reject_user_with_reason(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->customer()->create(['verification_status' => 'pending']);

        $this->postJson(self::ENDPOINT.'/'.$user->id.'/verify', [
            'action' => 'reject',
            'rejection_reason' => 'Blurry ID document',
        ])->assertStatus(200);

        $fresh = $user->fresh();
        $this->assertSame('rejected', $fresh->verification_status);
        $this->assertSame('Blurry ID document', $fresh->rejection_reason);
    }

    public function test_reject_requires_a_reason(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->customer()->create();

        $this->postJson(self::ENDPOINT.'/'.$user->id.'/verify', ['action' => 'reject'])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['rejection_reason']]);
    }

    public function test_verify_rejects_invalid_action(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->customer()->create();

        $this->postJson(self::ENDPOINT.'/'.$user->id.'/verify', ['action' => 'maybe'])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['action']]);
    }

    // ---------------------------------------------------------------------
    // Update (requires users.edit)
    // ---------------------------------------------------------------------

    public function test_admin_with_users_edit_can_update_status(): void
    {
        $this->actingAsAdminWith(['users.edit']);
        $user = User::factory()->customer()->create(['status' => 'active']);

        $this->putJson(self::ENDPOINT.'/'.$user->id, ['status' => 'inactive'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertSame('inactive', $user->fresh()->status);
    }

    public function test_admin_with_users_edit_can_sync_roles(): void
    {
        $this->actingAsAdminWith(['users.edit']);
        Role::create(['name' => 'Support', 'code' => 'support', 'guard_name' => 'sanctum']);
        $user = User::factory()->operation()->create();

        $this->putJson(self::ENDPOINT.'/'.$user->id, ['roles' => ['Support']])
            ->assertStatus(200);

        $this->assertTrue($user->fresh()->hasRole('Support'));
    }

    public function test_update_rejects_invalid_status(): void
    {
        $this->actingAsAdminWith(['users.edit']);
        $user = User::factory()->customer()->create();

        $this->putJson(self::ENDPOINT.'/'.$user->id, ['status' => 'banned'])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['status']]);
    }

    public function test_admin_with_only_view_cannot_update(): void
    {
        // Granular gating: users.view does not grant users.edit.
        $this->actingAsAdminWith(['users.view']);
        $user = User::factory()->customer()->create(['status' => 'active']);

        $this->putJson(self::ENDPOINT.'/'.$user->id, ['status' => 'inactive'])
            ->assertStatus(403);
        $this->assertSame('active', $user->fresh()->status);
    }

    // ---------------------------------------------------------------------
    // Toggle status (requires users.edit)
    // ---------------------------------------------------------------------

    public function test_admin_can_toggle_user_status(): void
    {
        $this->actingAsAdminWith(['users.edit']);
        $user = User::factory()->customer()->create(['status' => 'active']);

        $this->patchJson(self::ENDPOINT.'/'.$user->id.'/toggle-status')
            ->assertStatus(200);
        $this->assertSame('inactive', $user->fresh()->status);
    }

    public function test_admin_cannot_toggle_own_status(): void
    {
        $admin = $this->actingAsAdminWith(['users.edit']);

        // Safety guard: an admin cannot toggle their own account.
        $this->patchJson(self::ENDPOINT.'/'.$admin->id.'/toggle-status')
            ->assertStatus(403);
        $this->assertSame('active', $admin->fresh()->status);
    }

    // ---------------------------------------------------------------------
    // Reset password (requires users.edit)
    // ---------------------------------------------------------------------

    public function test_admin_can_reset_user_password(): void
    {
        $this->actingAsAdminWith(['users.edit']);
        $user = User::factory()->customer()->create();

        $this->postJson(self::ENDPOINT.'/'.$user->id.'/reset-password', [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(200);

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_operations_role_holder_can_manage_customers(): void
    {
        // The Operations Manager role carries users.edit (seeder + migration),
        // so operations staff can change a customer's status and reset their
        // password; KYC verify is open to any admin.
        $role = Role::firstOrCreate(
            ['name' => 'Operations Manager'],
            ['code' => 'OPS_MGR', 'description' => 'مدير العمليات', 'status' => 'active', 'guard_name' => 'sanctum'],
        );
        $role->givePermissionTo(Permission::findOrCreate('users.edit', 'sanctum'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $operations = User::factory()->operation()->create();
        $operations->assignRole($role);
        $this->actingAs($operations, 'sanctum');

        $customer = User::factory()->customer()->create(['status' => 'active']);

        // Change status.
        $this->patchJson(self::ENDPOINT.'/'.$customer->id.'/toggle-status')->assertStatus(200);
        $this->assertSame('inactive', $customer->fresh()->status);

        // Reset password.
        $this->postJson(self::ENDPOINT.'/'.$customer->id.'/reset-password', [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(200);

        // Accept / reject KYC.
        $this->postJson(self::ENDPOINT.'/'.$customer->id.'/verify', ['action' => 'approve'])->assertStatus(200);
    }

    public function test_reset_password_requires_confirmation(): void
    {
        $this->actingAsAdminWith(['users.edit']);
        $user = User::factory()->customer()->create();

        $this->postJson(self::ENDPOINT.'/'.$user->id.'/reset-password', [
            'password' => 'new-password-123',
            'password_confirmation' => 'mismatch',
        ])->assertStatus(422)
            ->assertJsonStructure(['params' => ['password']]);
    }

    public function test_reset_password_rejects_short_password(): void
    {
        $this->actingAsAdminWith(['users.edit']);
        $user = User::factory()->customer()->create();

        $this->postJson(self::ENDPOINT.'/'.$user->id.'/reset-password', [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)
            ->assertJsonStructure(['params' => ['password']]);
    }
}
