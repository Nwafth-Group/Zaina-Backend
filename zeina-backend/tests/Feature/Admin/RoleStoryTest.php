<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class RoleStoryTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/roles';
    private const ROLE_PERMISSIONS = ['roles.view', 'roles.create', 'roles.edit', 'roles.delete'];

    protected function setUp(): void
    {
        parent::setUp();
        // Create basic permissions
        foreach (self::ROLE_PERMISSIONS as $perm) {
            Permission::findOrCreate($perm, 'sanctum');
        }
    }

    private function getSuperAdmin(): User
    {
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'sanctum'],
            ['code' => 'SUPER_ADMIN', 'status' => 'active']
        );
        $superAdminRole->syncPermissions(Permission::all());

        $admin = User::factory()->superAdmin()->create();
        $admin->assignRole($superAdminRole);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($admin, 'sanctum');

        return $admin;
    }

    private function getAdminWithPermissions(array $permissions): User
    {
        $adminRole = Role::firstOrCreate(
            ['name' => 'CMS Admin', 'guard_name' => 'sanctum'],
            ['code' => 'CMS_ADMIN', 'status' => 'active']
        );
        $adminRole->syncPermissions($permissions);

        $admin = User::factory()->operation()->create();
        $admin->assignRole($adminRole);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($admin, 'sanctum');

        return $admin;
    }

    public function test_guest_cannot_access_roles(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_customer_cannot_access_roles(): void
    {
        $this->actingAsCustomer();
        $this->getJson(self::ENDPOINT)->assertStatus(403);
    }

    public function test_super_admin_can_view_create_and_manage_roles(): void
    {
        $this->getSuperAdmin();

        // 1. List
        $response = $this->getJson(self::ENDPOINT);
        $response->assertStatus(200);

        // 2. Create
        $response = $this->postJson(self::ENDPOINT, [
            'name' => 'Manager X',
            'code' => 'MGR_X',
            'description' => 'Manager level X',
            'status' => 'active',
            'permissions' => ['roles.view'],
        ]);
        $response->assertStatus(201);
        $roleId = $response->json('data.id');

        // 3. Edit (Update)
        $this->putJson(self::ENDPOINT . '/' . $roleId, [
            'name' => 'Manager X Updated',
            'code' => 'MGR_X',
            'description' => 'Updated desc',
            'status' => 'inactive',
            'permissions' => ['roles.view', 'roles.create'],
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Manager X Updated');

        // 4. Duplicate
        $response = $this->postJson(self::ENDPOINT . '/' . $roleId . '/duplicate');
        $response->assertStatus(200);
        $duplicateRoleId = $response->json('data.id');

        // 5. Delete (Destroy)
        $this->deleteJson(self::ENDPOINT . '/' . $duplicateRoleId)
            ->assertStatus(200);
    }

    public function test_admin_without_super_admin_cannot_edit_or_delete_roles(): void
    {
        // CMS Admin with view and create permissions
        $this->getAdminWithPermissions(['roles.view', 'roles.create']);

        // Create a test role to work with
        $role = Role::create([
            'name' => 'Operations Y',
            'code' => 'OPS_Y',
            'status' => 'active',
            'guard_name' => 'sanctum',
        ]);

        // Trying to edit (Update) must return 403 Forbidden
        $this->putJson(self::ENDPOINT . '/' . $role->id, [
            'name' => 'Operations Y Updated',
            'code' => 'OPS_Y',
            'status' => 'active',
            'permissions' => ['roles.view'],
        ])
            ->assertStatus(403);

        // Trying to delete (Destroy) must return 403 Forbidden
        $this->deleteJson(self::ENDPOINT . '/' . $role->id)
            ->assertStatus(403);

        // Trying to duplicate must return 403 Forbidden
        $this->postJson(self::ENDPOINT . '/' . $role->id . '/duplicate')
            ->assertStatus(403);
    }

    public function test_cannot_delete_role_assigned_to_users(): void
    {
        $this->getSuperAdmin();

        $role = Role::create([
            'name' => 'Operations Z',
            'code' => 'OPS_Z',
            'status' => 'active',
            'guard_name' => 'sanctum',
        ]);

        // Assign user to this role
        $user = User::factory()->operation()->create();
        $user->assignRole($role);

        // Delete should be blocked
        $this->deleteJson(self::ENDPOINT . '/' . $role->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_duplicate_role_name_or_code_is_rejected(): void
    {
        $this->getSuperAdmin();

        Role::create([
            'name' => 'Role Unique A',
            'code' => 'UNIQUE_A',
            'status' => 'active',
            'guard_name' => 'sanctum',
        ]);

        // Create duplicate role name
        $this->postJson(self::ENDPOINT, [
            'name' => 'Role Unique A',
            'code' => 'UNIQUE_B',
            'status' => 'active',
            'permissions' => ['roles.view'],
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['name']]);

        // Create duplicate role code
        $this->postJson(self::ENDPOINT, [
            'name' => 'Role Unique B',
            'code' => 'UNIQUE_A',
            'status' => 'active',
            'permissions' => ['roles.view'],
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['code']]);
    }

    public function test_grouped_permissions_list_endpoint(): void
    {
        $this->getSuperAdmin();

        $response = $this->getJson(self::ENDPOINT . '/permissions');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'roles' => [
                        ['id', 'name', 'action']
                    ]
                ]
            ]);
    }
}
