<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Service;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Step 3 of the Zaina admin workflow: managing Workers (the people who perform
 * services; linked to services via the `service_workers` pivot).
 *
 * Routes: apiResource `/api/v1/admin/workers` + POST `{worker}/toggle-status`,
 * all behind `auth:sanctum` + `IsAdmin`. The focus of this suite is confirming
 * that every admin-managed field round-trips into the database.
 */
class WorkerManagementTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/workers';

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_guest_cannot_list_workers(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_customer_cannot_access_admin_workers(): void
    {
        $this->actingAsCustomer();

        $this->getJson(self::ENDPOINT)->assertStatus(403);
    }

    public function test_admin_can_list_workers_paginated(): void
    {
        $this->actingAsAdmin();
        Worker::factory()->count(3)->create();

        $this->getJson(self::ENDPOINT)
            ->assertStatus(200)
            ->assertJsonCount(3, 'data.items')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [['id', 'name', 'nationality', 'rating', 'status']],
                    'links',
                    'meta',
                ],
            ]);
    }

    // ---------------------------------------------------------------------
    // Store — every managed column must persist
    // ---------------------------------------------------------------------

    public function test_admin_can_create_a_worker_and_all_fields_persist(): void
    {
        $this->actingAsAdmin();

        // Worker create returns 201 (unlike Category which returns 200).
        $response = $this->postJson(self::ENDPOINT, [
            'name' => 'Layla Hassan',
            'nationality' => 'Egypt',
            'status' => 'inactive',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Layla Hassan')
            ->assertJsonPath('data.nationality', 'Egypt')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('workers', [
            'name' => 'Layla Hassan',
            'nationality' => 'Egypt',
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_link_worker_to_services_on_create(): void
    {
        $this->actingAsAdmin();
        $services = Service::factory()->count(2)->create();

        $response = $this->postJson(self::ENDPOINT, [
            'name' => 'Mona Adel',
            'nationality' => 'Palestine',
            'services' => $services->pluck('id')->toArray(),
        ]);

        $response->assertStatus(201);
        $worker = Worker::where('name', 'Mona Adel')->firstOrFail();

        foreach ($services as $service) {
            $this->assertDatabaseHas('service_workers', [
                'worker_id' => $worker->id,
                'service_id' => $service->id,
            ]);
        }
    }

    public function test_create_worker_defaults_status_to_active_in_db(): void
    {
        $this->actingAsAdmin();

        // Status omitted -> DB applies the 'active' default. (As with Category,
        // the immediate response serializes the un-refreshed model, so
        // data.status comes back null — documented here.)
        $response = $this->postJson(self::ENDPOINT, [
            'name' => 'Sara Omar',
            'nationality' => 'India',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.status', null);
        $this->assertSame('active', Worker::where('name', 'Sara Omar')->value('status'));
    }

    public function test_create_worker_requires_name(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::ENDPOINT, ['nationality' => 'Egypt'])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['name']]);
    }

    public function test_create_worker_requires_nationality(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::ENDPOINT, ['name' => 'No Nationality'])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['nationality']]);
    }

    public function test_create_worker_rejects_invalid_status(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::ENDPOINT, [
            'name' => 'Bad Status',
            'nationality' => 'Egypt',
            'status' => 'on_leave',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['status']]);
    }

    public function test_create_worker_rejects_unknown_service_id(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::ENDPOINT, [
            'name' => 'Ghost Link',
            'nationality' => 'Egypt',
            'services' => [99999],
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['services.0']]);
    }

    // ---------------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------------

    public function test_admin_can_update_worker_fields(): void
    {
        $this->actingAsAdmin();
        $worker = Worker::factory()->create(['name' => 'Old Name', 'status' => 'active']);

        $this->putJson(self::ENDPOINT.'/'.$worker->id, [
            'name' => 'New Name',
            'nationality' => 'Vietnam',
            'status' => 'inactive',
        ])->assertStatus(200);

        $this->assertDatabaseHas('workers', [
            'id' => $worker->id,
            'name' => 'New Name',
            'nationality' => 'Vietnam',
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_resync_worker_services_on_update(): void
    {
        $this->actingAsAdmin();
        $worker = Worker::factory()->create();
        [$serviceA, $serviceB] = Service::factory()->count(2)->create()->all();
        $worker->services()->attach($serviceA->id);

        // sync() replaces the old link set with the new one.
        $this->putJson(self::ENDPOINT.'/'.$worker->id, [
            'services' => [$serviceB->id],
        ])->assertStatus(200);

        $this->assertDatabaseHas('service_workers', [
            'worker_id' => $worker->id,
            'service_id' => $serviceB->id,
        ]);
        $this->assertDatabaseMissing('service_workers', [
            'worker_id' => $worker->id,
            'service_id' => $serviceA->id,
        ]);
    }

    public function test_show_returns_the_workers_linked_services(): void
    {
        // Regression: the edit form reads data.services to pre-select checkboxes.
        // WorkerResource exposes services via whenLoaded, so show() must eager-load
        // the relationship or the linked services come back empty (looks "not saved").
        $this->actingAsAdmin();
        $worker = Worker::factory()->create();
        $services = Service::factory()->count(2)->create();
        $worker->services()->sync($services->pluck('id')->toArray());

        $response = $this->getJson(self::ENDPOINT.'/'.$worker->id)
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.services');

        $returnedIds = collect($response->json('data.services'))->pluck('id')->sort()->values()->all();
        $this->assertEquals($services->pluck('id')->sort()->values()->all(), $returnedIds);
    }

    public function test_update_response_includes_the_resynced_services(): void
    {
        $this->actingAsAdmin();
        $worker = Worker::factory()->create();
        $service = Service::factory()->create();

        $this->putJson(self::ENDPOINT.'/'.$worker->id, [
            'services' => [$service->id],
        ])
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.services.0.id', $service->id);
    }

    public function test_service_id_none_filters_workers_with_no_services(): void
    {
        $this->actingAsAdmin();
        $service = Service::factory()->create();

        $linked = Worker::factory()->create(['name' => 'Has Service']);
        $linked->services()->sync([$service->id]);
        Worker::factory()->create(['name' => 'No Service']);

        $response = $this->getJson(self::ENDPOINT.'?service_id=none')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $names = collect($response->json('data.items'))->pluck('name')->all();
        $this->assertContains('No Service', $names);
        $this->assertNotContains('Has Service', $names);
    }

    // ---------------------------------------------------------------------
    // Details modal — worker profile + latest bookings + reviews
    // ---------------------------------------------------------------------

    public function test_guest_cannot_view_worker_details(): void
    {
        $worker = Worker::factory()->create();

        $this->getJson(self::ENDPOINT.'/'.$worker->id.'/details')->assertStatus(401);
    }

    public function test_worker_details_returns_profile_stats_bookings_and_reviews(): void
    {
        $this->actingAsAdmin();
        $worker = Worker::factory()->create(['name' => 'Profile Worker']);

        // Two active assignments (one completed, one upcoming).
        $completed = Booking::factory()->create(['status' => 'completed']);
        $upcoming = Booking::factory()->create(['status' => 'Upcoming']);
        $worker->bookings()->attach($completed->id, ['status' => 'active']);
        $worker->bookings()->attach($upcoming->id, ['status' => 'active']);

        Review::factory()->forWorker($worker)->create(['rating' => 5, 'comment' => 'Excellent']);
        Review::factory()->forWorker($worker)->create(['rating' => 3, 'comment' => 'Okay']);

        $this->getJson(self::ENDPOINT.'/'.$worker->id.'/details')
            ->assertStatus(200)
            ->assertJsonPath('data.worker.id', $worker->id)
            ->assertJsonPath('data.worker.name', 'Profile Worker')
            ->assertJsonPath('data.stats.total_bookings', 2)
            ->assertJsonPath('data.stats.completed_bookings', 1)
            ->assertJsonPath('data.stats.reviews_count', 2)
            ->assertJsonPath('data.stats.avg_rating', 4)
            ->assertJsonCount(2, 'data.latest_bookings')
            ->assertJsonCount(2, 'data.reviews')
            ->assertJsonStructure([
                'data' => [
                    'worker' => ['id', 'name', 'nationality', 'rating', 'status'],
                    'stats' => ['total_bookings', 'completed_bookings', 'reviews_count', 'avg_rating'],
                    'latest_bookings' => [['id', 'booking_ref', 'customer_name', 'service_name', 'status']],
                    'reviews' => [['id', 'rating', 'comment', 'customer' => ['id', 'first_name', 'last_name'], 'created_since']],
                ],
            ]);
    }

    public function test_worker_details_only_counts_active_assignments(): void
    {
        $this->actingAsAdmin();
        $worker = Worker::factory()->create();

        $active = Booking::factory()->create();
        $removed = Booking::factory()->create();
        $worker->bookings()->attach($active->id, ['status' => 'active']);
        // A reassigned worker keeps a 'replaced' pivot row that must not count.
        $worker->assignments()->create(['booking_id' => $removed->id, 'status' => 'replaced']);

        $this->getJson(self::ENDPOINT.'/'.$worker->id.'/details')
            ->assertStatus(200)
            ->assertJsonPath('data.stats.total_bookings', 1)
            ->assertJsonCount(1, 'data.latest_bookings');
    }

    public function test_worker_details_for_worker_without_history_is_empty(): void
    {
        $this->actingAsAdmin();
        $worker = Worker::factory()->create();

        $this->getJson(self::ENDPOINT.'/'.$worker->id.'/details')
            ->assertStatus(200)
            ->assertJsonPath('data.stats.total_bookings', 0)
            ->assertJsonPath('data.stats.reviews_count', 0)
            ->assertJsonPath('data.stats.avg_rating', 0)
            ->assertJsonCount(0, 'data.latest_bookings')
            ->assertJsonCount(0, 'data.reviews');
    }

    // ---------------------------------------------------------------------
    // Toggle status
    // ---------------------------------------------------------------------

    public function test_admin_can_toggle_worker_status(): void
    {
        $this->actingAsAdmin();
        $worker = Worker::factory()->create(['status' => 'active']);

        $this->postJson(self::ENDPOINT.'/'.$worker->id.'/toggle-status')->assertStatus(200);
        $this->assertSame('inactive', $worker->fresh()->status);

        $this->postJson(self::ENDPOINT.'/'.$worker->id.'/toggle-status')->assertStatus(200);
        $this->assertSame('active', $worker->fresh()->status);
    }

    // ---------------------------------------------------------------------
    // Characterization of known gaps (flagged, not fixed)
    // ---------------------------------------------------------------------

    /**
     * Regression: WorkerResource now maps `rating` => `avg_rating`. Previously it
     * read a non-existent `rating` attribute, so the worker rating was always null.
     */
    public function test_worker_rating_reflects_avg_rating(): void
    {
        $this->actingAsAdmin();
        $worker = Worker::factory()->create(['avg_rating' => 4.5]);

        $this->getJson(self::ENDPOINT.'/'.$worker->id)
            ->assertStatus(200)
            ->assertJsonPath('data.rating', 4.5);
    }

    /**
     * GAP: WorkerController::destroy is an empty stub, so DELETE does not remove
     * the worker. Documented here so a future implementation flips this test.
     */
    public function test_destroy_worker_is_currently_a_noop(): void
    {
        $this->actingAsAdmin();
        $worker = Worker::factory()->create();

        $this->deleteJson(self::ENDPOINT.'/'.$worker->id);

        $this->assertDatabaseHas('workers', ['id' => $worker->id]);
    }
}
