<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\Category;
use App\Models\City;
use App\Models\Service;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Step 2 of the Zaina admin workflow: managing Services and all their related
 * configuration (durations, rules, coverages, prices, worker links). The focus is
 * confirming the create/update flow persists into every related table, with the
 * field names matching the actual DB columns.
 */
class ServiceManagementTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/services';

    /**
     * A valid create payload for an hourly service, scoped to the given city.
     *
     * @return array<string, mixed>
     */
    private function hourlyPayload(int $cityId, array $workerIds, array $overrides = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->create()->id,
            'type' => 'hourly',
            'name_ar' => 'تنظيف منزلي',
            'name_en' => 'Home Cleaning',
            'description_en' => 'A full home cleaning service.',
            'status' => 'active',
            'features' => [['ar' => 'سريع', 'en' => 'Fast']],
            'workers' => $workerIds,
            'durations' => ['service_period_hours' => 2],
            'rules' => [
                'free_cancel_cutoff_hours' => 2,
                'reschedule_cutoff_hours' => 3,
                'lead_time_min_hours' => 1,
                'lead_time_max_days' => 7,
                'late_cancel_action' => 'fee',
                'late_cancel_fee_type' => 'fixed',
                'late_cancel_fee_value' => 10,
                'no_show_policy' => 'consumed',
                'max_changes_per_booking' => 2,
            ],
            'coverages' => [
                ['city_id' => $cityId, 'area_id' => null, 'status' => 'active'],
            ],
            'prices' => [
                ['pricing_scope' => 'city', 'city_id' => $cityId, 'area_id' => null, 'price_per_service_period' => 150, 'currency' => 'EGP'],
            ],
        ], $overrides);
    }

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_guest_cannot_list_services(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_customer_cannot_access_admin_services(): void
    {
        $this->actingAsCustomer();

        $this->getJson(self::ENDPOINT)->assertStatus(403);
    }

    public function test_admin_can_list_services(): void
    {
        $this->actingAsAdmin();
        Service::factory()->count(2)->create();

        $this->getJson(self::ENDPOINT)
            ->assertStatus(200)
            ->assertJsonStructure(['status', 'message', 'data' => ['items']]);
    }

    // ---------------------------------------------------------------------
    // Create — every related table must persist
    // ---------------------------------------------------------------------

    public function test_admin_can_create_a_full_hourly_service_persisting_all_relations(): void
    {
        $this->actingAsAdmin();
        $area = Area::factory()->create();
        $city = $area->city;
        $workers = Worker::factory()->count(2)->create();

        $payload = $this->hourlyPayload($city->id, $workers->pluck('id')->toArray());
        $response = $this->postJson(self::ENDPOINT, $payload);

        $response->assertStatus(201);
        $service = Service::where('name_en', 'Home Cleaning')->firstOrFail();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'type' => 'hourly',
            'category_id' => $payload['category_id'],
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('service_durations', [
            'service_id' => $service->id,
            'service_period_hours' => 2,
        ]);
        $this->assertDatabaseHas('service_rules', [
            'service_id' => $service->id,
            'late_cancel_action' => 'fee',
            'no_show_policy' => 'consumed',
            'max_changes_per_booking' => 2,
        ]);
        $this->assertDatabaseHas('service_coverages', [
            'service_id' => $service->id,
            'city_id' => $city->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('service_prices', [
            'service_id' => $service->id,
            'city_id' => $city->id,
            'currency' => 'EGP',
        ]);
        $this->assertSame('150.00', $service->priceRules()->first()->price_per_service_period);

        foreach ($workers as $worker) {
            $this->assertDatabaseHas('service_workers', [
                'service_id' => $service->id,
                'worker_id' => $worker->id,
            ]);
        }
    }

    public function test_admin_can_create_a_moqema_service_persisting_contract_durations(): void
    {
        $this->actingAsAdmin();
        $area = Area::factory()->create();
        $city = $area->city;
        $worker = Worker::factory()->create();

        $payload = $this->hourlyPayload($city->id, [$worker->id], [
            'type' => 'moqema',
            'name_en' => 'Monthly Maid',
            'name_ar' => 'عاملة شهرية',
            'durations' => ['allowed_contract_durations' => [1, 3, 6], 'min_contract_months' => 1],
        ]);

        $this->postJson(self::ENDPOINT, $payload)->assertStatus(201);

        // This specifically guards the ServiceDuration fillable fix: the contract
        // durations json column must be saved (it was previously dropped).
        $duration = Service::where('name_en', 'Monthly Maid')->firstOrFail()->duration;
        $this->assertSame([1, 3, 6], $duration->allowed_contract_durations);
        $this->assertSame(1, $duration->min_contract_months);
    }

    public function test_area_scoped_price_derives_city_from_area(): void
    {
        $this->actingAsAdmin();
        $area = Area::factory()->create();
        $city = $area->city;
        $worker = Worker::factory()->create();

        $payload = $this->hourlyPayload($city->id, [$worker->id], [
            'prices' => [
                // area-scoped price sends no city_id; the controller must derive it.
                ['pricing_scope' => 'area', 'area_id' => $area->id, 'price_per_service_period' => 200, 'currency' => 'EGP'],
            ],
        ]);

        $this->postJson(self::ENDPOINT, $payload)->assertStatus(201);

        $this->assertDatabaseHas('service_prices', [
            'area_id' => $area->id,
            'city_id' => $city->id, // derived from the area, never trusted from input
        ]);
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    public function test_create_service_requires_workers_coverages_and_prices(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();

        $this->postJson(self::ENDPOINT, [
            'category_id' => $category->id,
            'type' => 'hourly',
            'name_ar' => 'بدون علاقات',
            'name_en' => 'No Relations',
            'status' => 'active',
            'durations' => ['service_period_hours' => 2],
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['workers', 'coverages', 'prices']]);
    }

    public function test_create_service_requires_price_per_service_period(): void
    {
        $this->actingAsAdmin();
        $area = Area::factory()->create();
        $worker = Worker::factory()->create();

        $payload = $this->hourlyPayload($area->city_id, [$worker->id], [
            'prices' => [['pricing_scope' => 'city', 'city_id' => $area->city_id]],
        ]);

        $this->postJson(self::ENDPOINT, $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['prices.0.price_per_service_period']]);
    }

    public function test_create_service_rejects_invalid_type(): void
    {
        $this->actingAsAdmin();
        $area = Area::factory()->create();
        $worker = Worker::factory()->create();

        $payload = $this->hourlyPayload($area->city_id, [$worker->id], ['type' => 'weekly']);

        $this->postJson(self::ENDPOINT, $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['type']]);
    }

    // ---------------------------------------------------------------------
    // Update & show & toggle
    // ---------------------------------------------------------------------

    public function test_admin_can_update_service_and_resync_workers(): void
    {
        $this->actingAsAdmin();
        $area = Area::factory()->create();
        $workers = Worker::factory()->count(2)->create();
        $this->postJson(self::ENDPOINT, $this->hourlyPayload($area->city_id, $workers->pluck('id')->toArray()))
            ->assertStatus(201);
        $service = Service::where('name_en', 'Home Cleaning')->firstOrFail();

        $newWorker = Worker::factory()->create();
        $this->putJson(self::ENDPOINT.'/'.$service->id, [
            'name_en' => 'Updated Cleaning',
            'workers' => [$newWorker->id],
        ])->assertStatus(200);

        $this->assertDatabaseHas('services', ['id' => $service->id, 'name_en' => 'Updated Cleaning']);
        $this->assertDatabaseHas('service_workers', ['service_id' => $service->id, 'worker_id' => $newWorker->id]);
        $this->assertDatabaseMissing('service_workers', ['service_id' => $service->id, 'worker_id' => $workers->first()->id]);
    }

    public function test_show_returns_nested_configuration(): void
    {
        $this->actingAsAdmin();
        $area = Area::factory()->create();
        $worker = Worker::factory()->create();
        $this->postJson(self::ENDPOINT, $this->hourlyPayload($area->city_id, [$worker->id]))->assertStatus(201);
        $service = Service::where('name_en', 'Home Cleaning')->firstOrFail();

        $this->getJson(self::ENDPOINT.'/'.$service->id)
            ->assertStatus(200)
            ->assertJsonPath('data.duration.service_period_hours', 2)
            ->assertJsonPath('data.prices.0.price_per_service_period', '150.00')
            ->assertJsonStructure([
                'data' => [
                    'rules' => ['late_cancel_action'],
                    'coverages',
                    'prices' => [['price_per_service_period', 'currency']],
                    'workers',
                ],
            ]);
    }

    public function test_admin_can_toggle_service_status(): void
    {
        $this->actingAsAdmin();
        $service = Service::factory()->create(['status' => 'active']);

        $this->postJson(self::ENDPOINT.'/'.$service->id.'/toggle-status')->assertStatus(200);
        $this->assertSame('inactive', $service->fresh()->status);

        $this->postJson(self::ENDPOINT.'/'.$service->id.'/toggle-status')->assertStatus(200);
        $this->assertSame('active', $service->fresh()->status);
    }

    // ---------------------------------------------------------------------
    // Multiple features (JSON) + multiple location-based prices
    // ---------------------------------------------------------------------

    public function test_multiple_features_are_stored_as_a_json_array(): void
    {
        $this->actingAsAdmin();
        $area = Area::factory()->create();
        $worker = Worker::factory()->create();
        $features = [
            ['ar' => 'سريع', 'en' => 'Fast'],
            ['ar' => 'موثوق', 'en' => 'Reliable'],
            ['ar' => 'مضمون', 'en' => 'Guaranteed'],
        ];

        $this->postJson(self::ENDPOINT, $this->hourlyPayload($area->city_id, [$worker->id], ['features' => $features]))
            ->assertStatus(201);

        $service = Service::where('name_en', 'Home Cleaning')->firstOrFail();
        $this->assertEquals($features, $service->features); // cast to array, round-trips via JSON
    }

    public function test_admin_can_set_multiple_prices_across_locations(): void
    {
        $this->actingAsAdmin();
        $area = Area::factory()->create();
        $city = $area->city;
        $otherCity = City::factory()->create();
        $worker = Worker::factory()->create();

        $prices = [
            ['pricing_scope' => 'city', 'city_id' => $city->id, 'area_id' => null, 'price_per_service_period' => 100, 'currency' => 'EGP'],
            ['pricing_scope' => 'area', 'area_id' => $area->id, 'price_per_service_period' => 150, 'currency' => 'EGP'],
            ['pricing_scope' => 'city', 'city_id' => $otherCity->id, 'area_id' => null, 'price_per_service_period' => 120, 'currency' => 'EGP'],
        ];
        $coverages = [
            ['city_id' => $city->id, 'area_id' => null, 'status' => 'active'],
            ['city_id' => $otherCity->id, 'area_id' => null, 'status' => 'active'],
        ];

        $this->postJson(self::ENDPOINT, $this->hourlyPayload($city->id, [$worker->id], ['prices' => $prices, 'coverages' => $coverages]))
            ->assertStatus(201);

        $service = Service::where('name_en', 'Home Cleaning')->firstOrFail();
        $this->assertSame(3, $service->priceRules()->count());
        $this->assertDatabaseHas('service_prices', ['service_id' => $service->id, 'city_id' => $city->id, 'area_id' => null]);
        $this->assertDatabaseHas('service_prices', ['service_id' => $service->id, 'city_id' => $city->id, 'area_id' => $area->id]);
        $this->assertDatabaseHas('service_prices', ['service_id' => $service->id, 'city_id' => $otherCity->id]);
    }

    public function test_multiple_prices_for_the_same_location_with_effective_windows(): void
    {
        $this->actingAsAdmin();
        $area = Area::factory()->create();
        $city = $area->city;
        $worker = Worker::factory()->create();

        $prices = [
            ['pricing_scope' => 'city', 'city_id' => $city->id, 'area_id' => null, 'price_per_service_period' => 100, 'currency' => 'EGP', 'effective_start' => '2026-01-01', 'effective_end' => '2026-06-30'],
            ['pricing_scope' => 'city', 'city_id' => $city->id, 'area_id' => null, 'price_per_service_period' => 130, 'currency' => 'EGP', 'effective_start' => '2026-07-01', 'effective_end' => '2026-12-31'],
        ];

        $this->postJson(self::ENDPOINT, $this->hourlyPayload($city->id, [$worker->id], ['prices' => $prices]))
            ->assertStatus(201);

        $service = Service::where('name_en', 'Home Cleaning')->firstOrFail();
        $this->assertSame(2, $service->priceRules()->where('city_id', $city->id)->count());

        $summer = $service->priceRules()->where('price_per_service_period', 100)->firstOrFail();
        $this->assertSame('2026-01-01', $summer->effective_start->toDateString());
        $this->assertSame('2026-06-30', $summer->effective_end->toDateString());
    }
}
