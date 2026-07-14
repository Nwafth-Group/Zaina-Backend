<?php

namespace Tests\Feature\Admin;

use App\Models\Coupon;
use App\Models\User;
use App\Models\City;
use App\Models\Area;
use App\Models\Service;
use App\Models\CustomerAddress;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class CouponManagementTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsAdmin;

    private const ADMIN_ENDPOINT = '/api/v1/admin/coupons';

    public function test_guest_cannot_manage_coupons(): void
    {
        $this->getJson(self::ADMIN_ENDPOINT)->assertStatus(401);
        $this->postJson(self::ADMIN_ENDPOINT, [])->assertStatus(401);
    }

    public function test_admin_can_create_coupon_with_all_services_and_audience(): void
    {
        $this->actingAsAdmin();

        $payload = [
            'name' => 'General Coupon',
            'code' => 'GEN10',
            'type' => 'percentage',
            'value' => 10,
            'start_date' => now()->toDateString() . ' 00:00:00',
            'end_date' => now()->addDays(10)->toDateString() . ' 23:59:59',
            'usage_limit' => 100,
            'user_usage_limit' => 2,
            'is_for_all_users' => true,
            'is_for_all_cities' => true,
            'is_for_all_services' => true,
            'status' => 'active',
        ];

        $response = $this->postJson(self::ADMIN_ENDPOINT, $payload);
        $response->assertStatus(201);
        $response->assertJsonPath('data.code', 'GEN10');

        $this->assertDatabaseHas('coupons', [
            'name' => 'General Coupon',
            'code' => 'GEN10',
            'value' => 10,
            'is_for_all_cities' => true,
        ]);
    }

    public function test_admin_cannot_create_coupon_with_invalid_rules(): void
    {
        $this->actingAsAdmin();

        // 1. Duplicate code / name validation
        Coupon::factory()->create(['name' => 'Existing Name', 'code' => 'EXIST']);

        $payload = [
            'name' => 'Existing Name',
            'code' => 'EXIST',
            'type' => 'percentage',
            'value' => 150, // Invalid: percentage > 100
            'start_date' => now()->toDateString() . ' 00:00:00',
            'end_date' => now()->subDay()->toDateString() . ' 00:00:00', // Invalid: end < start
            'usage_limit' => 10,
            'user_usage_limit' => 15, // Invalid: user_usage_limit > usage_limit
            'is_for_all_users' => true,
            'is_for_all_cities' => true,
            'is_for_all_services' => true,
            'status' => 'active',
        ];

        $response = $this->postJson(self::ADMIN_ENDPOINT, $payload);
        $response->assertStatus(422);
        
        // Assert custom JSON shape for validation errors (in params key)
        $response->assertJsonStructure([
            'params' => [
                'name',
                'code',
                'value',
                'end_date',
                'user_usage_limit',
            ]
        ]);
    }

    public function test_admin_can_create_coupon_with_specific_rules(): void
    {
        $this->actingAsAdmin();

        $city = City::factory()->create();
        $area = Area::factory()->create(['city_id' => $city->id]);
        $service = Service::factory()->create();
        $customer = User::factory()->create();

        $payload = [
            'name' => 'Special Coupon',
            'code' => 'SPEC50',
            'type' => 'fixed',
            'value' => 50,
            'start_date' => now()->toDateString() . ' 00:00:00',
            'end_date' => now()->addDays(5)->toDateString() . ' 23:59:59',
            'usage_limit' => 10,
            'user_usage_limit' => 1,
            'is_for_all_users' => false,
            'users' => [$customer->id],
            'is_for_all_cities' => false,
            'cities' => [$city->id],
            'areas' => [$area->id],
            'is_for_all_services' => false,
            'services' => [
                [
                    'id' => $service->id,
                    'allowed_visit_types' => ['single_visit'],
                    'allowed_shifts' => [
                        ['start_time' => '08:00', 'end_time' => '12:00']
                    ],
                ]
            ],
            'status' => 'active',
        ];

        $response = $this->postJson(self::ADMIN_ENDPOINT, $payload);
        $response->assertStatus(201);

        $this->assertDatabaseHas('coupons', ['code' => 'SPEC50']);
        
        $coupon = Coupon::where('code', 'SPEC50')->first();
        $this->assertTrue($coupon->users()->where('users.id', $customer->id)->exists());
        $this->assertTrue($coupon->cities()->where('cities.id', $city->id)->exists());
        $this->assertTrue($coupon->areas()->where('areas.id', $area->id)->exists());
        $this->assertTrue($coupon->services()->where('services.id', $service->id)->exists());
    }

    public function test_admin_can_update_coupon(): void
    {
        $this->actingAsAdmin();
        $coupon = Coupon::factory()->create(['name' => 'Old Name', 'code' => 'OLD']);

        $payload = [
            'name' => 'New Name',
            'code' => 'NEW123',
            'type' => 'fixed',
            'value' => 20,
            'start_date' => now()->toDateString() . ' 00:00:00',
            'end_date' => now()->addDays(5)->toDateString() . ' 23:59:59',
            'usage_limit' => 50,
            'user_usage_limit' => 1,
            'is_for_all_users' => true,
            'is_for_all_cities' => true,
            'is_for_all_services' => true,
            'status' => 'inactive',
        ];

        $response = $this->putJson(self::ADMIN_ENDPOINT . "/{$coupon->id}", $payload);
        $response->assertStatus(200);

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'name' => 'New Name',
            'code' => 'NEW123',
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_delete_coupon(): void
    {
        $this->actingAsAdmin();
        $coupon = Coupon::factory()->create();

        $response = $this->deleteJson(self::ADMIN_ENDPOINT . "/{$coupon->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }
}
