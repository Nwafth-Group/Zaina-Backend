<?php

namespace Tests\Concerns;

use App\Models\User;

/**
 * Helpers for authenticating feature tests as the different Zaina actors.
 *
 * Reused across the admin management suites (categories, services, workers,
 * bookings). The admin routes sit behind `auth:sanctum` + `IsAdmin`, which only
 * allows users whose `type` is `operation` or `super_admin`.
 */
trait ActsAsAdmin
{
    /**
     * Authenticate as a super-admin employee and return the user.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function actingAsAdmin(array $overrides = []): User
    {
        $admin = User::factory()->superAdmin()->create($overrides);
        $this->actingAs($admin, 'sanctum');

        return $admin;
    }

    /**
     * Authenticate as a customer (rejected by the IsAdmin middleware).
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function actingAsCustomer(array $overrides = []): User
    {
        $customer = User::factory()->customer()->create($overrides);
        $this->actingAs($customer, 'sanctum');

        return $customer;
    }
}
