<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Grants the Operations Manager role the `users.edit` permission on an existing
 * database. This lets operations staff manage customers — change status and
 * reset passwords (both routes are gated by `permission:users.edit`). KYC
 * accept/reject (the verify route) is already open to any admin.
 *
 * A fresh install gets this via RolesAndUsersSeeder; existing databases need it
 * applied without re-running that seeder (which also generates users).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate('users.edit', 'sanctum');

        $operations = Role::where('name', 'Operations Manager')->where('guard_name', 'sanctum')->first();
        $operations?->givePermissionTo('users.edit');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $operations = Role::where('name', 'Operations Manager')->where('guard_name', 'sanctum')->first();
        $operations?->revokePermissionTo('users.edit');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
