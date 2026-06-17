<?php

namespace Tests\Feature\Admin;

use App\Models\Service;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Filters for the admin workers listing: search, status, nationality, service_id,
 * min_rating, sort_by/sort_dir, per_page, plus the nationalities options endpoint.
 */
class WorkerListingFilterTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/workers';

    public function test_search_by_name(): void
    {
        $this->actingAsAdmin();
        Worker::factory()->create(['name' => 'Mariam Saleh']);
        Worker::factory()->create(['name' => 'Omar Khaled']);

        $this->getJson(self::ENDPOINT.'?search=Mariam')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Mariam Saleh');
    }

    public function test_filter_by_status(): void
    {
        $this->actingAsAdmin();
        Worker::factory()->create(['status' => 'active']);
        Worker::factory()->inactive()->create(['name' => 'Inactive Worker']);

        $this->getJson(self::ENDPOINT.'?status=inactive')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Inactive Worker');
    }

    public function test_filter_by_nationality(): void
    {
        $this->actingAsAdmin();
        Worker::factory()->create(['nationality' => 'Egypt', 'name' => 'Masry']);
        Worker::factory()->create(['nationality' => 'India']);

        $this->getJson(self::ENDPOINT.'?nationality=Egypt')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Masry');
    }

    public function test_filter_by_linked_service(): void
    {
        $this->actingAsAdmin();
        $service = Service::factory()->create();
        $linked = Worker::factory()->create(['name' => 'Linked']);
        $linked->services()->attach($service->id);
        Worker::factory()->create(['name' => 'Unlinked']);

        $this->getJson(self::ENDPOINT.'?service_id='.$service->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Linked');
    }

    public function test_filter_by_min_rating(): void
    {
        $this->actingAsAdmin();
        Worker::factory()->create(['avg_rating' => 4.8, 'name' => 'Top']);
        Worker::factory()->create(['avg_rating' => 3.2, 'name' => 'Low']);

        $this->getJson(self::ENDPOINT.'?min_rating=4')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Top');
    }

    public function test_sort_by_rating_desc(): void
    {
        $this->actingAsAdmin();
        Worker::factory()->create(['avg_rating' => 3.0, 'name' => 'Low']);
        Worker::factory()->create(['avg_rating' => 5.0, 'name' => 'High']);

        $this->getJson(self::ENDPOINT.'?sort_by=avg_rating&sort_dir=desc')
            ->assertStatus(200)
            ->assertJsonPath('data.items.0.name', 'High')
            ->assertJsonPath('data.items.1.name', 'Low');
    }

    public function test_combined_filters_narrow_to_a_single_match(): void
    {
        $this->actingAsAdmin();
        $service = Service::factory()->create();

        $match = Worker::factory()->create([
            'name' => 'Match',
            'nationality' => 'Egypt',
            'status' => 'active',
            'avg_rating' => 4.9,
        ]);
        $match->services()->attach($service->id);

        // Noise that each fails at least one criterion.
        Worker::factory()->inactive()->create(['nationality' => 'Egypt', 'avg_rating' => 4.9]);
        Worker::factory()->create(['nationality' => 'India', 'avg_rating' => 4.9]);

        $this->getJson(self::ENDPOINT."?service_id={$service->id}&nationality=Egypt&status=active&min_rating=4.5")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Match');
    }

    public function test_per_page_limits_results(): void
    {
        $this->actingAsAdmin();
        Worker::factory()->count(5)->create();

        $this->getJson(self::ENDPOINT.'?per_page=2')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.meta.per_page', 2);
    }

    public function test_nationalities_endpoint_returns_distinct_sorted_values(): void
    {
        $this->actingAsAdmin();
        Worker::factory()->create(['nationality' => 'Egypt']);
        Worker::factory()->create(['nationality' => 'Egypt']);
        Worker::factory()->create(['nationality' => 'India']);

        $response = $this->getJson(self::ENDPOINT.'/nationalities')->assertStatus(200);

        $this->assertSame(['Egypt', 'India'], $response->json('data'));
    }

    public function test_nationalities_endpoint_requires_admin(): void
    {
        $this->actingAsCustomer();

        $this->getJson(self::ENDPOINT.'/nationalities')->assertStatus(403);
    }
}
