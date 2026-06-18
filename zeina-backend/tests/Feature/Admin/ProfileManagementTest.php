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

class ProfileManagementTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/profile';

    private const ME_ENDPOINT = '/api/v1/admin/me';

    public function test_guest_cannot_access_profile(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_guest_cannot_access_me(): void
    {
        $this->getJson(self::ME_ENDPOINT)->assertStatus(401);
    }

    public function test_me_returns_live_identity_roles_and_permissions(): void
    {
        $admin = $this->actingAsAdmin(['email' => 'me@zaina.test']);
        Permission::findOrCreate('employees.view', 'sanctum');
        $admin->givePermissionTo('employees.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->getJson(self::ME_ENDPOINT)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.email', 'me@zaina.test')
            ->assertJsonPath('data.type', 'super_admin')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'type', 'roles', 'permissions']]);

        $permissions = $this->getJson(self::ME_ENDPOINT)->json('data.permissions');
        $this->assertContains('employees.view', $permissions);
    }

    public function test_me_for_operations_user_excludes_employee_permissions(): void
    {
        // An operations user's live permissions never include employees.* — this
        // is what keeps the employees link hidden in the sidebar for them.
        foreach (['users.view', 'employees.view'] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }
        $opsRole = Role::create(['name' => 'Operations Manager', 'code' => 'ops', 'guard_name' => 'sanctum']);
        $opsRole->givePermissionTo('users.view');

        $ops = User::factory()->operation()->create();
        $ops->assignRole($opsRole);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($ops, 'sanctum');

        $permissions = $this->getJson(self::ME_ENDPOINT)
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'operation')
            ->json('data.permissions');

        $this->assertContains('users.view', $permissions);
        $this->assertNotContains('employees.view', $permissions);
    }

    public function test_admin_can_view_own_profile(): void
    {
        $admin = $this->actingAsAdmin(['email' => 'me@zaina.test']);

        $this->getJson(self::ENDPOINT)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.email', 'me@zaina.test');
    }

    public function test_admin_can_update_own_profile(): void
    {
        $admin = $this->actingAsAdmin();

        $this->putJson(self::ENDPOINT, [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'updated@zaina.test',
            'phone_number' => '0599999999',
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'first_name' => 'Updated',
            'email' => 'updated@zaina.test',
            'phone_number' => '0599999999',
        ]);
    }

    public function test_profile_update_rejects_an_email_used_by_another_user(): void
    {
        $this->actingAsAdmin();
        User::factory()->operation()->create(['email' => 'taken@zaina.test']);

        $this->putJson(self::ENDPOINT, [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'taken@zaina.test',
            'phone_number' => '0591111111',
        ])->assertStatus(422)->assertJsonStructure(['params' => ['email']]);
    }

    public function test_admin_can_change_own_password_with_correct_current_password(): void
    {
        $admin = $this->actingAsAdmin(['password' => Hash::make('oldsecret')]);

        $this->putJson(self::ENDPOINT.'/password', [
            'current_password' => 'oldsecret',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertStatus(200);

        $this->assertTrue(Hash::check('newsecret123', $admin->fresh()->password));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $this->actingAsAdmin(['password' => Hash::make('oldsecret')]);

        $this->putJson(self::ENDPOINT.'/password', [
            'current_password' => 'wrongpass',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertStatus(422)->assertJsonStructure(['params' => ['current_password']]);
    }

    public function test_change_password_requires_matching_confirmation(): void
    {
        $this->actingAsAdmin(['password' => Hash::make('oldsecret')]);

        $this->putJson(self::ENDPOINT.'/password', [
            'current_password' => 'oldsecret',
            'password' => 'newsecret123',
            'password_confirmation' => 'different',
        ])->assertStatus(422)->assertJsonStructure(['params' => ['password']]);
    }
}
