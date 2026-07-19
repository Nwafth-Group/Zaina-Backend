<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Booking;
use App\Models\City;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\ServiceCoverage;
use App\Models\ServiceDuration;
use App\Models\ServiceRule;
use App\Models\ServiceSchedule;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class BookingCouponTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_active_coupon_is_accepted_for_valid_conditions(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $city = City::factory()->create(['status' => 'active']);
        $area = Area::factory()->create(['city_id' => $city->id, 'status' => 'active']);

        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'is_default' => true,
            'is_active' => true,
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
            'is_active' => true,
        ]);

        $city2 = City::factory()->create(['status' => 'active']);
        $area2 = Area::factory()->create(['city_id' => $city2->id, 'status' => 'active']);
        $address2 = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area2->id,
            'is_default' => false,
            'is_active' => true,
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
            'status' => 'completed',
        ]);

        // User now has 3 completed bookings -> should pass
        $err = $coupon->checkEligibility($user, $service, 'single_visit', '10:00', '12:00', $address);
        $this->assertNull($err);
    }

    public function test_coupon_service_specific_visit_types(): void
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

        // Link service with allowed visit types
        $coupon->services()->attach($service->id, [
            'allowed_visit_types' => json_encode(['three_visits_per_week']),
        ]);

        // 1. Ineligible Service (another service)
        $anotherService = Service::factory()->create(['status' => 'active']);
        $err = $coupon->checkEligibility($user, $anotherService, 'three_visits_per_week', '08:00', '12:00', $address);
        $this->assertEquals('coupon_service_ineligible', $err);

        // 2. Eligible service, but ineligible visit type (single_visit)
        $err = $coupon->checkEligibility($user, $service, 'single_visit', '08:00', '12:00', $address);
        $this->assertEquals('coupon_visit_type_ineligible', $err);

        // 3. Eligible everything
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

        // Scenario 1: Customer has no completed bookings -> excluded from V1
        $response = $this->getJson('/api/v1/customer/coupons');
        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.items'));

        // Scenario 2: Create completed bookings, but customer address is in different city -> excluded from V1
        Booking::factory()->count(2)->create([
            'customer_id' => $user->id,
            'status' => 'completed',
        ]);

        $anotherCity = City::factory()->create(['status' => 'active']);
        $anotherArea = Area::factory()->create(['city_id' => $anotherCity->id, 'status' => 'active']);
        CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $anotherArea->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/customer/coupons');
        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.items'));

        // Scenario 3: Update customer default address to match eligible city -> coupon returned in V1
        CustomerAddress::where('user_id', $user->id)->update(['is_default' => false]);
        CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/customer/coupons');
        $response->assertStatus(200);
        $response->assertJsonFragment(['code' => $coupon->code]);
    }

    public function test_customer_coupon_index_includes_already_used_coupons(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);

        // Create an inactive or expired coupon that the user has already used in a booking
        $coupon = Coupon::factory()->create([
            'is_for_all_users' => true,
            'status' => 'inactive', 
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(5), 
        ]);

        Booking::factory()->create([
            'customer_id' => $user->id,
            'coupon_id' => $coupon->id,
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/v1/customer/coupons');
        $response->assertStatus(200);

        // Verify that the coupon is retrieved because it was used by the customer
        $response->assertJsonFragment(['code' => $coupon->code]);
        
        // Also check that it has correct attributes
        $data = $response->json('data.items');
        $this->assertNotEmpty($data);
        
        // The coupon should be tagged as used and NOT available
        $item = collect($data)->firstWhere('code', $coupon->code);
        $this->assertNotNull($item);
        $this->assertTrue($item['is_used']);
        $this->assertFalse($item['is_available']);
    }

    public function test_customer_can_apply_coupon_to_booking_draft(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $city = City::factory()->create(['status' => 'active']);
        $area = Area::factory()->create(['city_id' => $city->id, 'status' => 'active']);

        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $service = Service::factory()->create([
            'status' => 'active',
            'base_price' => 200.00,
            'min_workers' => 1,
            'max_workers' => 3,
        ]);

        ServiceDuration::factory()->create([
            'service_id' => $service->id,
            'service_period_hours' => 2,
        ]);

        $dayName = strtolower(now()->addDay()->format('l'));
        ServiceSchedule::factory()->create([
            'service_id' => $service->id,
            'day' => $dayName,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'is_active' => true,
        ]);

        ServiceCoverage::factory()->create([
            'service_id' => $service->id,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'status' => 'active',
        ]);

        $worker = Worker::factory()->create(['status' => 'active']);
        $service->workers()->attach($worker->id);

        $coupon = Coupon::factory()->create([
            'code' => 'COUPON50',
            'type' => 'fixed',
            'value' => 50.00,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'status' => 'active',
            'is_for_all_users' => true,
            'is_for_all_cities' => true,
            'is_for_all_services' => true,
        ]);

        // Validate booking details with coupon code
        $payload = [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'dates' => [now()->addDay()->toDateString()],
            'start_time' => '10:00',
            'workers_count' => 1,
            'coupon_code' => 'COUPON50',
        ];

        $response = $this->postJson('/api/v1/customer/bookings/validate', $payload);
        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'data' => [
                'worker_ids',
                'pricing' => [
                    'subtotal',
                    'tax',
                    'discount',
                    'coupon_discount_amount',
                    'total',
                ],
            ],
        ]);

        $this->assertEquals(200.00, $response->json('data.pricing.subtotal'));
        $this->assertEquals(50.00, $response->json('data.pricing.coupon_discount_amount'));
        $this->assertEquals(150.00, $response->json('data.pricing.total'));
    }

    public function test_customer_bookings_index_includes_expected_arrival_time_and_schedule_dates(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $city = City::factory()->create(['status' => 'active']);
        $area = Area::factory()->create(['city_id' => $city->id, 'status' => 'active']);
        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $service = Service::factory()->create([
            'status' => 'active',
            'base_price' => 120.00,
        ]);

        ServiceRule::factory()->create([
            'service_id' => $service->id,
            'arrival_buffer_minutes' => 15,
            'late_arrival_grace_minutes' => 30,
        ]);

        $booking = Booking::create([
            'booking_ref' => 'BK-INDEX-TEST',
            'customer_id' => $user->id,
            'service_id' => $service->id,
            'address_id' => $address->id,
            'booking_date' => '2026-08-15',
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'duration_minutes' => 120,
            'total_amount' => 120.00,
            'price_subtotal' => 120.00,
            'status' => 'Upcoming',
            'payment_status' => 'paid',
            'payment_method' => 'wallet',
        ]);

        $response = $this->getJson('/api/v1/customer/bookings');
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'items' => [
                    [
                        'booking_id',
                        'expected_arrival_time',
                        'first_schedule_date',
                        'first_schedule_date_formatted',
                    ],
                ],
            ],
        ]);

        // 14:00 - 15 mins buffer = 13:45 (1:45 PM); 14:00 + 30 mins grace = 14:30 (2:30 PM)
        $this->assertEquals('1:45 PM - 2:30 PM', $response->json('data.items.0.expected_arrival_time'));
        $this->assertEquals('2026-08-15', $response->json('data.items.0.first_schedule_date'));
        $this->assertStringContainsString('15 Aug 2026', $response->json('data.items.0.first_schedule_date_formatted'));
    }
}
