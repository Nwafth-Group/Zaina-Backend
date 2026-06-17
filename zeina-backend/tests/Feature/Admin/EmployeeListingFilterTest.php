<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Filters for the admin employees listing: search (name/email/phone), type,
 * status, role, and sort_by/sort_dir. Only the internal staff user types
 * (operation, super_admin, driver) are listed.
 *
 * The employees area is gated by the `employees.view` permission (Super Admin
 * only), so the acting admin here is granted it explicitly.
 */
class EmployeeListingFilterTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/employees';

    /**
     * Authenticate as a super_admin holding the employees.view permission.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function actingAsEmployeeManager(array $overrides = []): User
    {
        Permission::findOrCreate('employees.view', 'sanctum');

        $admin = User::factory()->superAdmin()->create($overrides);
        $admin->givePermissionTo('employees.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($admin, 'sanctum');

        return $admin;
    }

    public function test_listing_excludes_customers(): void
    {
        $this->actingAsEmployeeManager();
        User::factory()->operation()->create(['first_name' => 'Staff']);
        User::factory()->customer()->create(['first_name' => 'Client']);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk();
        $names = collect($response->json('data.items'))->pluck('first_name');
        $this->assertTrue($names->contains('Staff'));
        $this->assertFalse($names->contains('Client'));
    }

    public function test_search_by_name_email_or_phone(): void
    {
        $this->actingAsEmployeeManager();
        User::factory()->operation()->create(['first_name' => 'Mariam', 'last_name' => 'Saleh', 'email' => 'mariam@zaina.test']);
        User::factory()->operation()->create(['first_name' => 'Omar', 'last_name' => 'Khaled', 'email' => 'omar@zaina.test']);

        $this->getJson(self::ENDPOINT.'?search=mariam@zaina.test')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.first_name', 'Mariam');
    }

    public function test_filter_by_type(): void
    {
        $this->actingAsEmployeeManager();
        User::factory()->operation()->create(['first_name' => 'Ops']);
        User::factory()->create(['first_name' => 'Driver', 'type' => 'driver']);

        $this->getJson(self::ENDPOINT.'?type=driver')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.first_name', 'Driver');
    }

    public function test_filter_by_status(): void
    {
        $this->actingAsEmployeeManager();
        User::factory()->operation()->create(['first_name' => 'ActiveOne', 'status' => 'active']);
        User::factory()->operation()->create(['first_name' => 'InactiveOne', 'status' => 'inactive']);

        $this->getJson(self::ENDPOINT.'?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.first_name', 'InactiveOne');
    }

    public function test_filter_by_role(): void
    {
        $this->actingAsEmployeeManager();
        // The User model authenticates on the `sanctum` guard, so roles assigned
        // to employees must share that guard.
        $role = Role::create(['name' => 'Support', 'code' => 'support', 'guard_name' => 'sanctum']);

        $withRole = User::factory()->operation()->create(['first_name' => 'Supporter']);
        $withRole->assignRole($role);
        User::factory()->operation()->create(['first_name' => 'NoRole']);

        $this->getJson(self::ENDPOINT.'?role=Support')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.first_name', 'Supporter');
    }

    public function test_sort_by_first_name_ascending(): void
    {
        // Pin the acting admin's name last so it doesn't lead the sorted page.
        $this->actingAsEmployeeManager(['first_name' => 'Zzz']);
        User::factory()->operation()->create(['first_name' => 'Zaid']);
        User::factory()->operation()->create(['first_name' => 'Adam']);

        $this->getJson(self::ENDPOINT.'?sort_by=first_name&sort_dir=asc')
            ->assertOk()
            ->assertJsonPath('data.items.0.first_name', 'Adam');
    }

    public function test_unknown_sort_column_is_ignored(): void
    {
        $this->actingAsEmployeeManager();
        User::factory()->operation()->create(['first_name' => 'Solo']);

        // A bogus sort_by must not error (allow-list falls back to created_at).
        $this->getJson(self::ENDPOINT.'?sort_by=password&sort_dir=asc')
            ->assertOk()
            ->assertJsonFragment(['first_name' => 'Solo']);
    }
}
