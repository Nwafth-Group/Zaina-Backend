<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rolls the employee-management permissions into an existing database.
 *
 * Employee routes are gated by `permission:employees.*` (Super Admin only).
 * A fresh install gets these through RolesAndUsersSeeder, but existing
 * databases need them applied without re-running that seeder (which also
 * generates users). This migration creates the permissions on the `sanctum`
 * guard and grants them to the Super Admin role via Spatie.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $permissions = ['employees.view', 'employees.create', 'employees.edit'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'sanctum')->first();
        $superAdmin?->givePermissionTo($this->permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'sanctum')->first();
        $superAdmin?->revokePermissionTo($this->permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
