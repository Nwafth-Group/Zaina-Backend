<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Locks in the location lookup behaviour the service/worker form dropdowns rely on:
 *
 *  - any admin can READ cities & areas (no location.edit permission needed), and
 *  - an area lookup scoped to a city returns that city's FULL set (not a page of 15),
 *
 * while location WRITES stay gated behind the location.edit permission.
 */
class LocationLookupTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    public function test_admin_without_location_permission_can_list_cities(): void
    {
        $this->actingAsAdmin();
        City::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/admin/cities');

        $response->assertOk();
        $this->assertCount(3, $response->json('data.items'));
    }

    public function test_admin_without_location_permission_can_list_areas_for_a_city(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        Area::factory()->count(2)->create(['city_id' => $city->id]);
        Area::factory()->count(4)->create(); // other cities — must be excluded

        $response = $this->getJson("/api/v1/admin/areas?city_id={$city->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_area_lookup_returns_all_areas_for_a_city_without_pagination(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        Area::factory()->count(20)->create(['city_id' => $city->id]);

        $response = $this->getJson("/api/v1/admin/areas?city_id={$city->id}");

        $response->assertOk();
        // The old paginate(15) would have capped this at 15 and broken the dropdown.
        $this->assertCount(20, $response->json('data.items'));
    }

    public function test_creating_a_city_still_requires_the_location_edit_permission(): void
    {
        $this->actingAsAdmin(); // super_admin, but holds no Spatie permissions

        $response = $this->postJson('/api/v1/admin/cities', [
            'name_ar' => 'مدينة',
            'name_en' => 'City',
        ]);

        $response->assertForbidden();
    }

    public function test_creating_an_area_still_requires_the_location_edit_permission(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/admin/areas', [
            'city_id' => $city->id,
            'name_ar' => 'منطقة',
            'name_en' => 'Area',
        ]);

        $response->assertForbidden();
    }

    public function test_guest_cannot_list_cities(): void
    {
        City::factory()->create();

        $this->getJson('/api/v1/admin/cities')->assertUnauthorized();
    }
}
