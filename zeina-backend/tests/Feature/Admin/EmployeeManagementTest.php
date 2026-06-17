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
 * Admin management of Employees (internal staff: operation / super_admin /
 * driver). Routes: apiResource `/api/v1/admin/employees` (minus destroy) +
 * PATCH `{employee}/toggle-status`, all behind `auth:sanctum` + `IsAdmin`.
 *
 * Employees have no hard delete — their lifecycle is the activity status, so
 * the destroy verb is unregistered (covered below). Roles are assigned through
 * Spatie; the User model authenticates on the `sanctum` guard, so every role
 * must be created on that guard to be assignable.
 */
class EmployeeManagementTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/employees';

    /** Employee management permissions, held only by the Super Admin role. */
    private const EMPLOYEE_PERMISSIONS = ['employees.view', 'employees.create', 'employees.edit'];

    private function makeRole(string $name): Role
    {
        return Role::create([
            'name' => $name,
            'code' => strtolower(str_replace(' ', '_', $name)),
            'guard_name' => 'sanctum',
        ]);
    }

    /**
     * Authenticate as a super_admin holding the employee-management permissions.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function actingAsEmployeeManager(array $overrides = []): User
    {
        foreach (self::EMPLOYEE_PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        $admin = User::factory()->superAdmin()->create($overrides);
        $admin->givePermissionTo(self::EMPLOYEE_PERMISSIONS);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($admin, 'sanctum');

        return $admin;
    }

    /**
     * A valid create payload. The referenced role must exist first.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Nour',
            'last_name' => 'Adel',
            'phone_number' => '0590000001',
            'email' => 'nour.adel@zaina.test',
            'password' => 'secret123',
            'type' => 'operation',
            'status' => 'active',
            'roles' => ['Support'],
        ], $overrides);
    }

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_guest_cannot_list_employees(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_customer_cannot_access_admin_employees(): void
    {
        $this->actingAsCustomer();

        $this->getJson(self::ENDPOINT)->assertStatus(403);
    }

    public function test_admin_without_employees_view_permission_cannot_list(): void
    {
        // Passes IsAdmin (super_admin type) but lacks the employees.view permission.
        $this->actingAsAdmin();

        $this->getJson(self::ENDPOINT)->assertStatus(403);
    }

    public function test_admin_without_employees_create_permission_cannot_create(): void
    {
        $this->actingAsAdmin();
        $this->makeRole('Support');

        $this->postJson(self::ENDPOINT, $this->validPayload())->assertStatus(403);
    }

    public function test_operations_manager_cannot_list_or_manage_employees(): void
    {
        // An operations employee carries only its own permissions (users/bookings),
        // never the employees.* set — so the whole employees area is closed to it.
        Permission::findOrCreate('users.view', 'sanctum');
        $opsRole = $this->makeRole('Operations Manager');
        $opsRole->givePermissionTo('users.view');

        $ops = User::factory()->operation()->create();
        $ops->assignRole($opsRole);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($ops, 'sanctum');

        $this->getJson(self::ENDPOINT)->assertStatus(403);
        $this->postJson(self::ENDPOINT, $this->validPayload())->assertStatus(403);
    }

    public function test_admin_can_list_employees_paginated(): void
    {
        $this->actingAsEmployeeManager();
        User::factory()->operation()->count(3)->create();

        // The acting super_admin is itself an employee type, so it appears in
        // the listing alongside the 3 created staff (4 total).
        $this->getJson(self::ENDPOINT)
            ->assertStatus(200)
            ->assertJsonCount(4, 'data.items')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [['id', 'first_name', 'last_name', 'email', 'type', 'status', 'primary_role']],
                    'links',
                    'meta',
                ],
            ]);
    }

    public function test_admin_can_filter_employees_by_role(): void
    {
        $this->actingAsEmployeeManager();
        $this->makeRole('Operations Manager');
        $this->makeRole('Support');

        $ops = User::factory()->operation()->create(['first_name' => 'Omar']);
        $ops->assignRole('Operations Manager');

        $other = User::factory()->operation()->create(['first_name' => 'Sara']);
        $other->assignRole('Support');

        $response = $this->getJson(self::ENDPOINT.'?role=Operations Manager')->assertStatus(200);

        $names = collect($response->json('data.items'))->pluck('first_name')->all();
        $this->assertContains('Omar', $names);
        $this->assertNotContains('Sara', $names);
    }

    // ---------------------------------------------------------------------
    // Store
    // ---------------------------------------------------------------------

    public function test_admin_can_create_employee_and_fields_persist(): void
    {
        $this->actingAsEmployeeManager();
        $this->makeRole('Support');

        $response = $this->postJson(self::ENDPOINT, $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.first_name', 'Nour')
            ->assertJsonPath('data.type', 'operation')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.primary_role', 'Support');

        $this->assertDatabaseHas('users', [
            'email' => 'nour.adel@zaina.test',
            'type' => 'operation',
            'status' => 'active',
        ]);

        $employee = User::where('email', 'nour.adel@zaina.test')->firstOrFail();
        $this->assertTrue(Hash::check('secret123', $employee->password)); // password hashed, not stored raw
        $this->assertTrue($employee->hasRole('Support'));
    }

    public function test_create_requires_core_fields(): void
    {
        $this->actingAsEmployeeManager();

        $this->postJson(self::ENDPOINT, [])
            ->assertStatus(422)
            ->assertJsonStructure([
                'params' => ['first_name', 'last_name', 'phone_number', 'email', 'password', 'type', 'status', 'roles'],
            ]);
    }

    public function test_create_rejects_duplicate_email(): void
    {
        $this->actingAsEmployeeManager();
        $this->makeRole('Support');
        User::factory()->operation()->create(['email' => 'dupe@zaina.test']);

        $this->postJson(self::ENDPOINT, $this->validPayload(['email' => 'dupe@zaina.test']))
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['email']]);
    }

    public function test_create_rejects_duplicate_phone(): void
    {
        $this->actingAsEmployeeManager();
        $this->makeRole('Support');
        User::factory()->operation()->create(['phone_number' => '0591111119']);

        $this->postJson(self::ENDPOINT, $this->validPayload(['phone_number' => '0591111119']))
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['phone_number']]);
    }

    public function test_create_rejects_customer_type(): void
    {
        $this->actingAsEmployeeManager();
        $this->makeRole('Support');

        // `customer` is explicitly excluded; only operation/super_admin/driver allowed.
        $this->postJson(self::ENDPOINT, $this->validPayload(['type' => 'customer']))
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['type']]);
    }

    public function test_create_rejects_invalid_status(): void
    {
        $this->actingAsEmployeeManager();
        $this->makeRole('Support');

        $this->postJson(self::ENDPOINT, $this->validPayload(['status' => 'on_leave']))
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['status']]);
    }

    public function test_create_rejects_unknown_role(): void
    {
        $this->actingAsEmployeeManager();

        // No role created -> 'Ghost' fails the exists:roles,name rule.
        $this->postJson(self::ENDPOINT, $this->validPayload(['roles' => ['Ghost']]))
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['roles.0']]);
    }

    // ---------------------------------------------------------------------
    // Show
    // ---------------------------------------------------------------------

    public function test_admin_can_show_employee_with_roles(): void
    {
        $this->actingAsEmployeeManager();
        $role = $this->makeRole('Support');
        $employee = User::factory()->operation()->create();
        $employee->assignRole($role);

        $this->getJson(self::ENDPOINT.'/'.$employee->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $employee->id)
            ->assertJsonPath('data.primary_role', 'Support');
    }

    // ---------------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------------

    public function test_admin_can_update_employee_and_resync_roles(): void
    {
        $this->actingAsEmployeeManager();
        $this->makeRole('Support');
        $this->makeRole('Manager');
        $employee = User::factory()->operation()->create(['first_name' => 'Old']);
        $employee->assignRole('Support');

        $this->putJson(self::ENDPOINT.'/'.$employee->id, [
            'first_name' => 'New',
            'last_name' => $employee->last_name,
            'phone_number' => $employee->phone_number, // unchanged unique values ignore self
            'email' => $employee->email,
            'type' => 'super_admin',
            'status' => 'inactive',
            'roles' => ['Manager'],
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'first_name' => 'New',
            'type' => 'super_admin',
            'status' => 'inactive',
        ]);

        $fresh = $employee->fresh();
        $this->assertTrue($fresh->hasRole('Manager'));
        $this->assertFalse($fresh->hasRole('Support')); // syncRoles replaced the old set
    }

    public function test_update_without_password_keeps_existing_password(): void
    {
        $this->actingAsEmployeeManager();
        $this->makeRole('Support');
        $employee = User::factory()->operation()->create(['password' => Hash::make('original-pass')]);
        $employee->assignRole('Support');
        $originalHash = $employee->fresh()->password;

        $this->putJson(self::ENDPOINT.'/'.$employee->id, [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'phone_number' => $employee->phone_number,
            'email' => $employee->email,
            'type' => $employee->type,
            'status' => $employee->status,
            'roles' => ['Support'],
            // password omitted -> controller leaves it untouched
        ])->assertStatus(200);

        $this->assertSame($originalHash, $employee->fresh()->password);
        $this->assertTrue(Hash::check('original-pass', $employee->fresh()->password));
    }

    // ---------------------------------------------------------------------
    // Toggle status (the lifecycle mechanism)
    // ---------------------------------------------------------------------

    public function test_admin_can_toggle_employee_status(): void
    {
        $this->actingAsEmployeeManager();
        $employee = User::factory()->operation()->create(['status' => 'active']);

        $this->patchJson(self::ENDPOINT.'/'.$employee->id.'/toggle-status')->assertStatus(200);
        $this->assertSame('inactive', $employee->fresh()->status);

        $this->patchJson(self::ENDPOINT.'/'.$employee->id.'/toggle-status')->assertStatus(200);
        $this->assertSame('active', $employee->fresh()->status);
    }

    public function test_cannot_deactivate_last_active_super_admin(): void
    {
        $this->actingAsEmployeeManager();
        $role = $this->makeRole('Super Admin');
        $onlySuper = User::factory()->create(['type' => 'super_admin', 'status' => 'active']);
        $onlySuper->assignRole($role);

        // Only one active holder of the 'Super Admin' role -> cannot deactivate.
        // (The acting admin is super_admin by type but does not hold the role.)
        $this->patchJson(self::ENDPOINT.'/'.$onlySuper->id.'/toggle-status')
            ->assertStatus(403);
        $this->assertSame('active', $onlySuper->fresh()->status);
    }

    public function test_can_deactivate_super_admin_when_another_exists(): void
    {
        $this->actingAsEmployeeManager();
        $role = $this->makeRole('Super Admin');
        $first = User::factory()->create(['type' => 'super_admin', 'status' => 'active']);
        $second = User::factory()->create(['type' => 'super_admin', 'status' => 'active']);
        $first->assignRole($role);
        $second->assignRole($role);

        // Two active Super Admins -> deactivating one is allowed.
        $this->patchJson(self::ENDPOINT.'/'.$first->id.'/toggle-status')
            ->assertStatus(200);
        $this->assertSame('inactive', $first->fresh()->status);
    }

    // ---------------------------------------------------------------------
    // No hard delete
    // ---------------------------------------------------------------------

    public function test_delete_employee_is_not_supported(): void
    {
        $this->actingAsEmployeeManager();
        $employee = User::factory()->operation()->create();

        // Deletion is not a concept for employees; the destroy route is
        // unregistered, so DELETE resolves to 405 (method not allowed).
        $this->deleteJson(self::ENDPOINT.'/'.$employee->id)
            ->assertStatus(405);

        $this->assertDatabaseHas('users', ['id' => $employee->id]);
    }
}
