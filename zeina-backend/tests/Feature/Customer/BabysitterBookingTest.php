<?php

namespace Tests\Feature\Customer;

use App\Models\City;
use App\Models\Area;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceChildAgeGroup;
use App\Models\ServiceSchedule;
use App\Models\Worker;
use App\Models\CustomerAddress;
use App\Models\Booking;
use App\Models\BookingAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class BabysitterBookingTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsAdmin;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function createBabysitterService(int $cityId, int $areaId): Service
    {
        $babysitterCategory = Category::factory()->create(['name_en' => 'Babysitting Services', 'status' => 'active']);

        $service = Service::factory()->create([
            'category_id' => $babysitterCategory->id,
            'status' => 'active',
            'service_code' => 'BABYSITTER_HOURLY',
            'pricing_type' => 'per_hour',
            'base_price' => 70.00,
            'min_duration' => 4,
            'max_duration' => 6,
            'min_workers' => 1,
            'max_workers' => 5,
            'min_children' => 1,
            'max_children' => 6,
            'worker_gender_options' => ['Female'],
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
        ]);

        \App\Models\ServiceCoverage::create([
            'service_id' => $service->id,
            'city_id' => $cityId,
            'area_id' => $areaId,
            'status' => 'active',
        ]);

        // Rules
        $service->rules()->create([
            'allow_cancel' => true,
            'free_cancel_cutoff_hours' => 24,
            'allow_reschedule' => true,
            'reschedule_cutoff_hours' => 48,
            'allow_same_day' => true,
            'max_advance_booking_days' => 30
        ]);

        // Schedules
        $nextMonday = Carbon::now()->next(Carbon::MONDAY);
        $dayName = strtolower($nextMonday->format('l'));

        ServiceSchedule::create([
            'service_id' => $service->id,
            'day' => $dayName,
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
            'is_active' => true,
        ]);

        // Child Age Groups
        // Newborn to 2 Years: 0 to 24 months, per_child, 1 child, 1 worker
        ServiceChildAgeGroup::create([
            'service_id' => $service->id,
            'name_ar' => 'حديثي الولادة إلى سنتين',
            'name_en' => 'Newborn to 2 Years',
            'age_from' => 0,
            'age_to' => 24,
            'calculation_type' => 'per_child',
            'children_count' => 1,
            'required_worker_count' => 1,
            'status' => 'active',
        ]);

        // 2 to 7 Years: 25 to 84 months, ratio_based, 2 children, 1 worker
        ServiceChildAgeGroup::create([
            'service_id' => $service->id,
            'name_ar' => 'من 2 إلى 7 سنوات',
            'name_en' => '2 to 7 Years',
            'age_from' => 25,
            'age_to' => 84,
            'calculation_type' => 'ratio_based',
            'children_count' => 2,
            'required_worker_count' => 1,
            'status' => 'active',
        ]);

        return $service;
    }

    public function test_available_dates_endpoint_requires_parameters_for_babysitter(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $city = City::factory()->create(['status' => 'active']);
        $area = Area::factory()->create(['city_id' => $city->id, 'status' => 'active']);
        $service = $this->createBabysitterService($city->id, $area->id);

        $response = $this->getJson("/api/v1/customer/bookings/available-dates?" . http_build_query([
            'service_id' => $service->id,
        ]));
        $response->assertStatus(422)
            ->assertJsonStructure(['params' => ['address_id', 'start_time', 'duration', 'number_of_children', 'child_age_groups']]);
    }

    public function test_available_dates_endpoint_calculates_correct_workers_count_and_returns_dates(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $city = City::factory()->create(['status' => 'active']);
        $area = Area::factory()->create(['city_id' => $city->id, 'status' => 'active']);
        $service = $this->createBabysitterService($city->id, $area->id);

        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'is_default' => true,
        ]);

        $newbornGroup = $service->childAgeGroups->where('name_en', 'Newborn to 2 Years')->first();
        $toddlerGroup = $service->childAgeGroups->where('name_en', '2 to 7 Years')->first();

        // 3 female workers
        $female1 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $female2 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $female3 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $service->workers()->attach([$female1->id, $female2->id, $female3->id]);

        // Children: 1 Newborn, 2 Toddlers
        // Newborn calculation: 1 newborn = 1 worker
        // Toddlers calculation: 2 toddlers = 1 worker (ratio based 2 to 1)
        // Total required workers: 2
        $nextMonday = Carbon::now()->next(Carbon::MONDAY);

        $response = $this->getJson("/api/v1/customer/bookings/available-dates?" . http_build_query([
            'service_id' => $service->id,
            'address_id' => $address->id,
            'start_time' => '10:00',
            'duration' => 4,
            'number_of_children' => 3,
            'child_age_groups' => [$newbornGroup->id, $toddlerGroup->id, $toddlerGroup->id],
        ]));

        $response->assertStatus(200);
        $this->assertContains($nextMonday->toDateString() . ' 00:00:00', $response->json('data'));
    }

    public function test_booking_validation_succeeds_with_correct_babysitter_ratio(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $city = City::factory()->create(['status' => 'active']);
        $area = Area::factory()->create(['city_id' => $city->id, 'status' => 'active']);
        $service = $this->createBabysitterService($city->id, $area->id);

        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $newbornGroup = $service->childAgeGroups->where('name_en', 'Newborn to 2 Years')->first();
        $toddlerGroup = $service->childAgeGroups->where('name_en', '2 to 7 Years')->first();

        // 3 female workers
        $female1 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $female2 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $female3 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $service->workers()->attach([$female1->id, $female2->id, $female3->id]);

        $nextMonday = Carbon::now()->next(Carbon::MONDAY);

        // Validate booking details: 1 newborn, 2 toddlers => requires 2 babysitters.
        // Base Price should be: Hourly Rate (70) * Duration (4) * Workers (2) = 560 EGP.
        $response = $this->postJson("/api/v1/customer/bookings/validate", [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'dates' => [$nextMonday->toDateString()],
            'start_time' => '10:00',
            'duration' => 4,
            'number_of_children' => 3,
            'child_age_groups' => [$newbornGroup->id, $toddlerGroup->id, $toddlerGroup->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.pricing.subtotal', 560);
        $response->assertJsonPath('data.pricing.total', 560);
        $this->assertCount(2, $response->json('data.worker_ids'));
    }

    public function test_booking_validation_fails_if_decreasing_workers_count_below_minimum(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $city = City::factory()->create(['status' => 'active']);
        $area = Area::factory()->create(['city_id' => $city->id, 'status' => 'active']);
        $service = $this->createBabysitterService($city->id, $area->id);

        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $newbornGroup = $service->childAgeGroups->where('name_en', 'Newborn to 2 Years')->first();

        // newborn count = 2 => minimum babysitters = 2
        $nextMonday = Carbon::now()->next(Carbon::MONDAY);

        // Attempting to send workers_count = 1 should fail
        $response = $this->postJson("/api/v1/customer/bookings/validate", [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'dates' => [$nextMonday->toDateString()],
            'start_time' => '10:00',
            'duration' => 4,
            'number_of_children' => 2,
            'child_age_groups' => [$newbornGroup->id, $newbornGroup->id],
            'workers_count' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['params' => ['workers_count']]);
    }

    public function test_store_booking_saves_babysitter_details_and_child_age_groups(): void
    {
        $user = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
        $city = City::factory()->create(['status' => 'active']);
        $area = Area::factory()->create(['city_id' => $city->id, 'status' => 'active']);
        $service = $this->createBabysitterService($city->id, $area->id);

        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $newbornGroup = $service->childAgeGroups->where('name_en', 'Newborn to 2 Years')->first();
        $toddlerGroup = $service->childAgeGroups->where('name_en', '2 to 7 Years')->first();

        // 3 female workers
        $female1 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $female2 = Worker::factory()->create(['status' => 'active', 'gender' => 'female']);
        $service->workers()->attach([$female1->id, $female2->id]);

        $user->deposit(1000, 'Mock Balance');

        $nextMonday = Carbon::now()->next(Carbon::MONDAY);

        $response = $this->postJson('/api/v1/customer/bookings', [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'visit_type' => 'single_visit',
            'dates' => [$nextMonday->toDateString()],
            'start_time' => '10:00',
            'duration' => 4,
            'worker_ids' => [$female1->id, $female2->id],
            'number_of_children' => 3,
            'child_age_groups' => [$newbornGroup->id, $toddlerGroup->id, $toddlerGroup->id],
            'payment_method' => 'wallet',
            'note' => 'Child 1 has milk allergy.',
        ]);

        $response->assertStatus(201);

        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertEquals(3, $booking->number_of_children);
        $this->assertContains($newbornGroup->id, $booking->child_age_groups);
        $this->assertContains($toddlerGroup->id, $booking->child_age_groups);
        $this->assertEquals('Child 1 has milk allergy.', $booking->customer_notes);
        $this->assertEquals(560.00, $booking->total_amount);
        $this->assertEquals('Upcoming', $booking->status);
        $this->assertEquals('paid', $booking->payment_status);
    }
}
