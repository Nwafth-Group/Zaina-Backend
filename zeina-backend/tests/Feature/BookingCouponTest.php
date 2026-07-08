<?php

namespace Tests\Feature;

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

class BookingCouponTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsAdmin;

    public function test_active_coupon_is_accepted_for_valid_conditions(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $city = City::factory()->create(['status' => 'active']);
        $area = Area::factory()->create(['city_id' => $city->id, 'status' => 'active']);
        
        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'is_default' => true,
            'is_active' => true
        ]);

        $service = Service::factory()->create([
            'status' => 'active',
            'base_price' => 100.00,
            'available_visit_types' => [['type' => 'single_visit', 'discount' => 0]],
        ]);

        $coupon = Coupon::factory()->create([
            'name' => 'General Coupon',
            'code' => 'GEN10',
            'type' => 'percentage',
            'value' => 10,
            'is_for_all_users' => true,
            'is_for_all_cities' => true,
            'is_for_all_services' => true,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'usage_limit' => 100,
            'user_usage_limit' => 2,
        ]);

        // Call Coupon checkEligibility method
        $err = $coupon->checkEligibility($user, $service, 'single_visit', '10:00', '12:00', $address);
        $this->assertNull($err);
    }

    public function test_coupon_location_eligibility_city_and_area(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        
        $city1 = City::factory()->create(['status' => 'active']);
        $area1 = Area::factory()->create(['city_id' => $city1->id, 'status' => 'active']);
        $address1 = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area1->id,
            'is_default' => true,
            'is_active' => true
        ]);

        $city2 = City::factory()->create(['status' => 'active']);
        $area2 = Area::factory()->create(['city_id' => $city2->id, 'status' => 'active']);
        $address2 = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area2->id,
            'is_default' => false,
            'is_active' => true
        ]);

        $service = Service::factory()->create(['status' => 'active']);

        $coupon = Coupon::factory()->create([
            'name' => 'City 1 Coupon',
            'code' => 'CITY1ONLY',
            'type' => 'fixed',
            'value' => 10,
            'is_for_all_users' => true,
            'is_for_all_cities' => false,
            'is_for_all_services' => true,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);
        $coupon->cities()->attach($city1->id);

        // Address 1 (in City 1) should be eligible
        $err = $coupon->checkEligibility($user, $service, 'single_visit', '10:00', '12:00', $address1);
        $this->assertNull($err);

        // Address 2 (in City 2) should NOT be eligible
        $err = $coupon->checkEligibility($user, $service, 'single_visit', '10:00', '12:00', $address2);
        $this->assertEquals('coupon_location_ineligible', $err);
    }

    public function test_coupon_completed_bookings_count_eligibility(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $service = Service::factory()->create(['status' => 'active']);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $coupon = Coupon::factory()->create([
            'name' => 'Loyal Coupon',
            'code' => 'LOYAL3',
            'type' => 'percentage',
            'value' => 20,
            'is_for_all_users' => true,
            'is_for_all_cities' => true,
            'is_for_all_services' => true,
            'status' => 'active',
            'completed_bookings_count' => 3,
            'user_usage_limit' => 10,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        // User has 0 completed bookings -> should fail
        $err = $coupon->checkEligibility($user, $service, 'single_visit', '10:00', '12:00', $address);
        $this->assertEquals('coupon_insufficient_completed_bookings', $err);

        // Create 3 completed bookings for the user
        Booking::factory()->count(3)->create([
            'customer_id' => $user->id,
            'status' => 'completed'
        ]);

        // User now has 3 completed bookings -> should pass
        $err = $coupon->checkEligibility($user, $service, 'single_visit', '10:00', '12:00', $address);
        $this->assertNull($err);
    }

    public function test_coupon_service_specific_visit_types_and_shifts(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $service = Service::factory()->create(['status' => 'active']);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $coupon = Coupon::factory()->create([
            'name' => 'Specific Service Coupon',
            'code' => 'SVCSPEC',
            'type' => 'percentage',
            'value' => 20,
            'is_for_all_users' => true,
            'is_for_all_cities' => true,
            'is_for_all_services' => false,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        // Link service with allowed visit types and shifts
        $coupon->services()->attach($service->id, [
            'allowed_visit_types' => json_encode(['three_visits_per_week']),
            'allowed_shifts' => json_encode([
                ['start_time' => '08:00', 'end_time' => '12:00']
            ])
        ]);

        // 1. Ineligible Service (another service)
        $anotherService = Service::factory()->create(['status' => 'active']);
        $err = $coupon->checkEligibility($user, $anotherService, 'three_visits_per_week', '08:00', '12:00', $address);
        $this->assertEquals('coupon_service_ineligible', $err);

        // 2. Eligible service, but ineligible visit type (single_visit)
        $err = $coupon->checkEligibility($user, $service, 'single_visit', '08:00', '12:00', $address);
        $this->assertEquals('coupon_visit_type_ineligible', $err);

        // 3. Eligible service and visit type, but ineligible shift (13:00 to 17:00)
        $err = $coupon->checkEligibility($user, $service, 'three_visits_per_week', '13:00', '17:00', $address);
        $this->assertEquals('coupon_shift_ineligible', $err);

        // 4. Eligible everything (exact matching shift 08:00 to 12:00)
        $err = $coupon->checkEligibility($user, $service, 'three_visits_per_week', '08:00', '12:00', $address);
        $this->assertNull($err);
    }

    public function test_v1_customer_coupon_index_does_not_filter_geography(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $city = City::factory()->create(['status' => 'active']);
        $coupon = Coupon::factory()->create([
            'is_for_all_users' => true,
            'is_for_all_cities' => false,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);
        $coupon->cities()->attach($city->id);

        $response = $this->getJson('/api/v1/customer/coupons');
        $response->assertStatus(200);
        $response->assertJsonFragment(['code' => $coupon->code]);
    }

    public function test_v2_customer_coupon_index_filters_geography_and_bookings(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $city = City::factory()->create(['status' => 'active']);
        $area = Area::factory()->create(['city_id' => $city->id, 'status' => 'active']);

        $coupon = Coupon::factory()->create([
            'is_for_all_users' => true,
            'is_for_all_cities' => false,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'completed_bookings_count' => 2,
        ]);
        $coupon->cities()->attach($city->id);

        // Scenario 1: Customer has no completed bookings -> excluded from V2
        $response = $this->getJson('/api/v2/customer/coupons');
        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.items'));

        // Scenario 2: Create completed bookings, but customer address is in different city -> excluded from V2
        Booking::factory()->count(2)->create([
            'customer_id' => $user->id,
            'status' => 'completed'
        ]);
        
        $anotherCity = City::factory()->create(['status' => 'active']);
        $anotherArea = Area::factory()->create(['city_id' => $anotherCity->id, 'status' => 'active']);
        CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $anotherArea->id,
            'is_default' => true,
            'is_active' => true
        ]);

        $response = $this->getJson('/api/v2/customer/coupons');
        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.items'));

        // Scenario 3: Update customer default address to match eligible city -> coupon returned in V2
        CustomerAddress::where('user_id', $user->id)->update(['is_default' => false]);
        CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'is_default' => true,
            'is_active' => true
        ]);

        $response = $this->getJson('/api/v2/customer/coupons');
        $response->assertStatus(200);
        $response->assertJsonFragment(['code' => $coupon->code]);
    }
}
