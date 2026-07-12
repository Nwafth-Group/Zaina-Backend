<?php

namespace Tests\Feature\Customer;

use App\Models\City;
use App\Models\Area;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Models\Worker;
use App\Models\CustomerAddress;
use App\Models\Booking;
use App\Models\BookingAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class HospitalityBookingTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsAdmin;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_available_dates_endpoint_requires_parameters(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $service = Service::factory()->create([
            'status' => 'active',
            'service_code' => 'HOSPITALITY_WORKER_HOURLY',
            'min_duration' => 4,
            'max_duration' => 6,
            'min_workers' => 2,
            'max_workers' => 12,
            'worker_gender_options' => ['Female', 'Male'],
        ]);

        $response = $this->getJson("/api/v1/customer/bookings/available-dates");
        $response->assertStatus(422);
    }

    public function test_available_dates_endpoint_filters_by_gender_and_worker_count(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        
        $service = Service::factory()->create([
            'status' => 'active',
            'service_code' => 'HOSPITALITY_WORKER_HOURLY',
            'min_duration' => 4,
            'max_duration' => 6,
            'min_workers' => 2,
            'max_workers' => 12,
            'worker_gender_options' => ['Female', 'Male'],
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
        ]);

        // Create schedules for Monday (tomorrow or next week)
        $nextMonday = Carbon::now()->next(Carbon::MONDAY);
        $dayName = strtolower($nextMonday->format('l'));

        ServiceSchedule::create([
            'service_id' => $service->id,
            'day' => $dayName,
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
            'is_active' => true,
        ]);

        // Create 2 female workers
        $female1 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $female2 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $service->workers()->attach([$female1->id, $female2->id]);

        // Create 1 male worker
        $male1 = Worker::factory()->create(['status' => 'active', 'gender' => 'male']);
        $service->workers()->attach([$male1->id]);

        $city = City::factory()->create(['status' => 'active']);
        $area = Area::factory()->create(['city_id' => $city->id, 'status' => 'active']);
        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'is_default' => true,
        ]);

        // Query available dates for Female, workers_count = 2 (should return Monday date)
        $response = $this->getJson("/api/v1/customer/bookings/available-dates?" . http_build_query([
            'service_id' => $service->id,
            'address_id' => $address->id,
            'start_time' => '10:00',
            'duration' => 4,
            'worker_gender' => 'Female',
            'workers_count' => 2,
        ]));

        if ($response->status() !== 200) {
            dd($response->json() ?? $response->getContent());
        }
        $response->assertStatus(200);
        $this->assertContains($nextMonday->toDateString() . ' 00:00:00', $response->json('data'));

        // Query available dates for Male, workers_count = 2 (should be empty because only 1 male worker exists)
        $response = $this->getJson("/api/v1/customer/bookings/available-dates?" . http_build_query([
            'service_id' => $service->id,
            'address_id' => $address->id,
            'start_time' => '10:00',
            'duration' => 4,
            'worker_gender' => 'Male',
            'workers_count' => 2,
        ]));

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    public function test_booking_validation_calculates_hourly_pricing_and_requires_gender(): void
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
            'service_code' => 'HOSPITALITY_WORKER_HOURLY',
            'pricing_type' => 'per_hour',
            'base_price' => 100.00,
            'min_duration' => 4,
            'max_duration' => 6,
            'min_workers' => 2,
            'max_workers' => 12,
            'worker_gender_options' => ['Female', 'Male'],
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
        ]);

        \App\Models\ServiceCoverage::create([
            'service_id' => $service->id,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'status' => 'active',
        ]);

        $nextMonday = Carbon::now()->next(Carbon::MONDAY);
        $dayName = strtolower($nextMonday->format('l'));

        ServiceSchedule::create([
            'service_id' => $service->id,
            'day' => $dayName,
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
            'is_active' => true,
        ]);

        // Add 2 female workers
        $female1 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $female2 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $service->workers()->attach([$female1->id, $female2->id]);

        // Validate booking
        $response = $this->postJson("/api/v1/customer/bookings/validate", [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'dates' => [$nextMonday->toDateString()],
            'start_time' => '10:00',
            'duration' => 4,
            'workers_count' => 2,
            'worker_gender' => 'Female',
        ]);

        $response->assertStatus(200);
        // Price should be: Hourly Price (100) * Duration (4) * Workers Count (2) = 800
        $response->assertJsonPath('data.pricing.subtotal', 800);
        $response->assertJsonPath('data.pricing.total', 800);
        $this->assertCount(2, $response->json('data.worker_ids'));
    }

    public function test_store_booking_saves_gender_preference_and_workers_count(): void
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
            'service_code' => 'HOSPITALITY_WORKER_HOURLY',
            'pricing_type' => 'per_hour',
            'base_price' => 100.00,
            'min_duration' => 4,
            'max_duration' => 6,
            'min_workers' => 2,
            'max_workers' => 12,
            'worker_gender_options' => ['Female', 'Male'],
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
        ]);

        \App\Models\ServiceCoverage::create([
            'service_id' => $service->id,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'status' => 'active',
        ]);

        // Add 1500 SAR mock balance to wallet
        $user->deposit(1500, 'Mock Balance');

        $nextMonday = Carbon::now()->next(Carbon::MONDAY);
        $dayName = strtolower($nextMonday->format('l'));

        ServiceSchedule::create([
            'service_id' => $service->id,
            'day' => $dayName,
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
            'is_active' => true,
        ]);

        $female1 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $female2 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $service->workers()->attach([$female1->id, $female2->id]);

        // Store Booking
        $response = $this->postJson('/api/v1/customer/bookings', [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'visit_type' => 'single_visit',
            'dates' => [$nextMonday->toDateString()],
            'start_time' => '10:00',
            'duration' => 4,
            'worker_ids' => [$female1->id, $female2->id],
            'worker_gender' => 'Female',
            'payment_method' => 'wallet',
        ]);

        $response->assertStatus(201);
        
        // Assert database record has the saved attributes
        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertEquals('Female', $booking->worker_gender);
        $this->assertEquals(2, $booking->number_of_workers);
        $this->assertEquals(800.00, $booking->total_amount);
    }
}
