<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\Service;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class CustomerV2ServiceTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsAdmin;

    private const ADMIN_ENDPOINT = '/api/v1/admin/services';
    private const V1_CUSTOMER_ENDPOINT = '/api/v1/customer/services';
    private const V2_CUSTOMER_ENDPOINT = '/api/v2/customer/services';

    public function test_v2_customer_cannot_view_active_services_under_inactive_category(): void
    {
        $category = Category::factory()->create(['status' => 'inactive']);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'status' => 'active'
        ]);

        // V2 listing should be empty
        $response = $this->getJson(self::V2_CUSTOMER_ENDPOINT);
        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.items'));

        // V2 show service should return 404
        $response = $this->getJson(self::V2_CUSTOMER_ENDPOINT . '/' . $service->id);
        $response->assertStatus(404);
    }

    public function test_v2_customer_service_endpoints(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'available_visit_types' => [['type' => 'single_visit', 'discount' => 0]],
        ]);
        
        $worker = Worker::factory()->create(['status' => 'active']);
        $service->workers()->attach($worker->id);

        // 1. List is not paginated and does not have available_visit_types
        $response = $this->getJson(self::V2_CUSTOMER_ENDPOINT);
        $response->assertStatus(200);
        $response->assertJsonMissingPath('data.links');
        $response->assertJsonMissingPath('data.meta');
        $response->assertJsonStructure(['data' => ['items' => [['id', 'name_en', 'type']]]]);
        $this->assertNull($response->json('data.items.0.available_visit_types'));

        // 2. Available visit types endpoint returns visit types directly
        $response = $this->getJson(self::V2_CUSTOMER_ENDPOINT . "/{$service->id}/available-visit-types");
        $response->assertStatus(200);
        $response->assertJsonFragment(['type' => 'single_visit']);

        // 3. Show endpoint returns preferred_workers and available_dates
        $response = $this->getJson(self::V2_CUSTOMER_ENDPOINT . "/{$service->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'preferred_workers',
                'available_dates',
            ]
        ]);

        // 4. Deleted shifts and available-workers endpoints return 404
        $this->getJson(self::V2_CUSTOMER_ENDPOINT . "/{$service->id}/shifts?date=2026-07-20")->assertStatus(404);
        $this->getJson(self::V2_CUSTOMER_ENDPOINT . "/{$service->id}/available-workers?date=2026-07-12&start_time=09:00")->assertStatus(404);
    }

    public function test_v1_customer_available_workers_periods_count_is_required(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'status' => 'active'
        ]);

        // V1 endpoint: periods_count is required, should fail with 422 if missing
        $response = $this->getJson(self::V1_CUSTOMER_ENDPOINT . "/{$service->id}/available-workers?date=2026-07-06&start_time=10:00");
        $response->assertStatus(422);
        $response->assertJsonStructure(['params' => ['periods_count']]);
    }

    public function test_admin_cannot_update_service_code(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $category = Category::factory()->create(['status' => 'active']);
        
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'service_code' => 'SVC-DH-HOURLY',
            'description_ar' => 'وصف تفصيلي',
            'description_en' => 'Detailed description',
            'terms_ar' => 'شروط وأحكام',
            'terms_en' => 'Terms and conditions'
        ]);

        // Modify update payload to change service_code
        $updatePayload = [
            'category_id' => $category->id,
            'name_ar' => $service->name_ar,
            'name_en' => $service->name_en,
            'description_ar' => $service->description_ar,
            'description_en' => $service->description_en,
            'status' => 'active',
            'type' => 'hourly',
            'base_price' => 100.00,
            'default_duration' => 2,
            'min_workers' => 1,
            'max_workers' => 3,
            'terms_ar' => $service->terms_ar,
            'terms_en' => $service->terms_en,
            'working_days' => ['monday'],
            'shifts' => [['start_time' => '10:00']],
            'service_code' => 'SVC-DH-CHANGED' // changing it
        ];

        // Should return 422 error on validation
        $response = $this->putJson(self::ADMIN_ENDPOINT . '/' . $service->id, $updatePayload);
        $response->assertStatus(422);
        $response->assertJsonStructure(['params' => ['service_code']]);

        // Updating with the same service_code should pass
        $updatePayload['service_code'] = 'SVC-DH-HOURLY';
        $response = $this->putJson(self::ADMIN_ENDPOINT . '/' . $service->id, $updatePayload);
        $response->assertStatus(200);
    }

    public function test_admin_cannot_provide_custom_service_code_on_creation(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $category = Category::factory()->create(['status' => 'active']);

        $createPayload = [
            'category_id' => $category->id,
            'name_ar' => 'خدمة جديدة بالكامل',
            'name_en' => 'Brand New Service',
            'description_ar' => 'وصف تنظيف',
            'description_en' => 'cleaning description',
            'status' => 'active',
            'type' => 'hourly',
            'base_price' => 100.00,
            'default_duration' => 2,
            'min_workers' => 1,
            'max_workers' => 3,
            'terms_ar' => 'شروط وأحكام',
            'terms_en' => 'terms and conditions',
            'working_days' => ['monday'],
            'shifts' => [['start_time' => '10:00']],
            'service_code' => 'CUSTOM-CODE' // setting it manually
        ];

        // Should return 422 error on validation
        $response = $this->postJson(self::ADMIN_ENDPOINT, $createPayload);
        $response->assertStatus(422);
        $response->assertJsonStructure(['params' => ['service_code']]);
    }

    public function test_model_level_service_code_immutability(): void
    {
        $service = Service::factory()->create(['service_code' => 'SVC-DH-HOURLY']);
        
        $service->update(['service_code' => 'SVC-DH-MODIFIED']);
        
        // Assert it did not change in database
        $this->assertEquals('SVC-DH-HOURLY', $service->fresh()->service_code);
    }

    public function test_v2_customer_home_page_only_returns_banners_and_upcoming_appointment(): void
    {
        $response = $this->getJson('/api/v2/customer/home');
        $response->assertStatus(200);

        // Verify keys in the data envelope
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'banners',
                'upcoming_appointment',
            ]
        ]);

        // Assert categories and popular_services are NOT returned
        $data = $response->json('data');
        $this->assertArrayNotHasKey('categories', $data);
        $this->assertArrayNotHasKey('popular_services', $data);
    }
}
