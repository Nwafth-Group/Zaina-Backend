<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Service;
use App\Models\Worker;
use App\Models\City;
use App\Models\Area;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class ServiceConfigurationValidationTest extends TestCase
{
    use RefreshDatabase, ActsAsAdmin;

    private const ADMIN_ENDPOINT = '/api/v1/admin/services';
    private const CUSTOMER_ENDPOINT = '/api/v1/customer/services';

    /**
     * Create a valid service payload.
     */
    private function servicePayload(int $cityId, array $workerIds = [], array $overrides = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->create(['status' => 'active'])->id,
            'type' => 'hourly',
            'name_ar' => 'تنظيف جديد',
            'name_en' => 'New Cleaning Service',
            'description_ar' => 'وصف تفصيلي للخدمة الجديدة.',
            'description_en' => 'Detailed description for new service.',
            'status' => 'active',
            'features' => [['ar' => 'سريع', 'en' => 'Fast']],
            'workers' => $workerIds,
            'available_visit_types' => [
                ['type' => 'single_visit', 'discount' => 10],
                ['type' => '3_days_per_week', 'discount' => 15]
            ],
            'discount_value' => 10.00,
            'base_price' => 120.00,
            'default_duration' => 2,
            'min_duration' => 1,
            'max_duration' => 4,
            'min_workers' => 1,
            'max_workers' => 3,
            'terms_ar' => 'شروط وأحكام الخدمة',
            'terms_en' => 'Terms and conditions for service',
            'acceptance_required' => true,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'coverages' => [
                ['city_id' => $cityId, 'area_id' => null, 'status' => 'active']
            ],
            'prices' => [
                ['pricing_scope' => 'city', 'city_id' => $cityId, 'area_id' => null, 'price_per_service_period' => 120.00, 'currency' => 'EGP']
            ],
            'working_days' => ['monday', 'tuesday'],
            'shifts' => [
                ['start_time' => '10:00']
            ],
            'rules' => [
                'allow_cancel' => true,
                'free_cancel_cutoff_hours' => 24,
                'allow_reschedule' => true,
                'reschedule_cutoff_hours' => 48,
                'allow_same_day' => false,
                'max_advance_booking_days' => 30
            ]
        ], $overrides);
    }


    public function test_service_name_must_be_unique_under_same_category(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $categoryA = Category::factory()->create(['status' => 'active']);
        $categoryB = Category::factory()->create(['status' => 'active']);

        // Create first service under Category A
        $payload1 = $this->servicePayload($city->id, [], [
            'category_id' => $categoryA->id,
            'name_ar' => 'خدمة فريدة',
            'name_en' => 'Unique Service'
        ]);
        $this->postJson(self::ADMIN_ENDPOINT, $payload1)->assertStatus(201);

        // Try creating another service with same name under same Category A - should fail validation
        $payload2 = $this->servicePayload($city->id, [], [
            'category_id' => $categoryA->id,
            'name_ar' => 'خدمة فريدة',
            'name_en' => 'Unique Service'
        ]);
        $this->postJson(self::ADMIN_ENDPOINT, $payload2)
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['name_ar', 'name_en']]);

        // Create service with same name under Category B - should succeed
        $payload3 = $this->servicePayload($city->id, [], [
            'category_id' => $categoryB->id,
            'name_ar' => 'خدمة فريدة',
            'name_en' => 'Unique Service'
        ]);
        $this->postJson(self::ADMIN_ENDPOINT, $payload3)->assertStatus(201);
    }

    public function test_service_shifts_must_be_within_request_time_window(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();

        // Shift start time (08:00) before service start time (09:00) - should fail
        $payload1 = $this->servicePayload($city->id, [], [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'shifts' => [
                ['start_time' => '08:00']
            ]
        ]);
        $this->postJson(self::ADMIN_ENDPOINT, $payload1)
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['shifts.0.start_time']]);

        // Shift end time (19:00, start at 17:00 + default_duration 2) after service end time (18:00) - should fail
        $payload2 = $this->servicePayload($city->id, [], [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'default_duration' => 2,
            'shifts' => [
                ['start_time' => '17:00']
            ]
        ]);
        $this->postJson(self::ADMIN_ENDPOINT, $payload2)
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['shifts.0.start_time']]);

        // Valid shift times - should succeed
        $payload3 = $this->servicePayload($city->id, [], [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'shifts' => [
                ['start_time' => '10:00']
            ]
        ]);
        $this->postJson(self::ADMIN_ENDPOINT, $payload3)->assertStatus(201);
    }

    public function test_assigned_workers_must_be_active(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $inactiveWorker = Worker::factory()->create(['status' => 'inactive']);

        $payload = $this->servicePayload($city->id, [$inactiveWorker->id]);
        $this->postJson(self::ADMIN_ENDPOINT, $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['workers.0']]);
    }

    public function test_min_duration_cannot_be_greater_than_max_duration(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();

        // min_duration (3) > max_duration (2) - should fail validation
        $payload = $this->servicePayload($city->id, [], [
            'min_duration' => 3,
            'max_duration' => 2,
        ]);

        $this->postJson(self::ADMIN_ENDPOINT, $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['max_duration']]);
    }

    public function test_working_days_and_shifts_are_persisted_correctly(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();

        $payload = $this->servicePayload($city->id, [], [
            'default_duration' => 3,
            'working_days' => ['sunday', 'monday'],
            'shifts' => [
                ['start_time' => '09:00'],
                ['start_time' => '13:00']
            ]
        ]);

        $response = $this->postJson(self::ADMIN_ENDPOINT, $payload);
        $response->assertStatus(201);

        $serviceId = $response->json('data.id');
        
        // We assert using the service_schedules table
        $this->assertDatabaseHas('service_schedules', [
            'service_id' => $serviceId,
            'day' => 'sunday',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->assertDatabaseHas('service_schedules', [
            'service_id' => $serviceId,
            'day' => 'monday',
            'start_time' => '13:00:00',
            'end_time' => '16:00:00',
        ]);
    }
}
