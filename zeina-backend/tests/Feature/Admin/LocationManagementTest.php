<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\Govrenrate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Admin management of the location hierarchy: Country -> Governorate -> City -> Area.
 * Read lookups (cities/areas/govrenrates index) are open to any admin; all writes
 * and the countries listing are gated behind the `location.edit` permission.
 */
class LocationManagementTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    /**
     * Super-admin that actually holds the location.edit permission.
     */
    private function actingAsLocationManager(): User
    {
        $admin = User::factory()->superAdmin()->create();
        $admin->givePermissionTo(Permission::findOrCreate('location.edit', 'sanctum'));
        $this->actingAs($admin, 'sanctum');

        return $admin;
    }

    // ---------------------------------------------------------------- Countries

    public function test_guest_cannot_list_countries(): void
    {
        $this->getJson('/api/v1/admin/countries')->assertStatus(401);
    }

    public function test_admin_without_location_permission_cannot_list_countries(): void
    {
        $this->actingAsAdmin(); // super_admin type but no location.edit permission

        $this->getJson('/api/v1/admin/countries')->assertStatus(403);
    }

    public function test_location_manager_lists_countries_with_city_count(): void
    {
        $this->actingAsLocationManager();
        $country = Country::factory()->create();
        City::factory()->create(['govrenrate_id' => Govrenrate::factory()->create(['country_id' => $country->id])->id]);

        $this->getJson('/api/v1/admin/countries')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['items' => [['id', 'name_ar', 'name_en', 'code', 'cities_count', 'status', 'flag']]]]);
    }

    public function test_location_manager_can_create_country_with_flag(): void
    {
        Storage::fake('public');
        $this->actingAsLocationManager();

        $response = $this->postJson('/api/v1/admin/countries', [
            'name_ar' => 'الأردن',
            'name_en' => 'Jordan',
            'code' => 'JO',
            'currency_code' => 'JOD',
            'phone_code' => '+962',
            'flag' => UploadedFile::fake()->image('flag.png'),
        ]);

        $response->assertStatus(200)->assertJsonPath('data.name_en', 'Jordan');
        $this->assertNotNull($response->json('data.flag'));
        $this->assertDatabaseHas('countries', ['code' => 'JO', 'name_en' => 'Jordan']);
    }

    public function test_create_country_rejects_duplicate_code(): void
    {
        $this->actingAsLocationManager();
        Country::factory()->create(['code' => 'EG']);

        $this->postJson('/api/v1/admin/countries', [
            'name_ar' => 'مصر', 'name_en' => 'Egypt', 'code' => 'EG', 'currency_code' => 'EGP',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['code']]);
    }

    public function test_location_manager_can_update_and_toggle_country(): void
    {
        $this->actingAsLocationManager();
        $country = Country::factory()->create(['name_en' => 'Old', 'status' => 'active']);

        $this->putJson('/api/v1/admin/countries/'.$country->id, [
            'name_ar' => 'جديد', 'name_en' => 'New', 'code' => $country->code,
        ])->assertStatus(200)->assertJsonPath('data.name_en', 'New');

        $this->postJson('/api/v1/admin/countries/'.$country->id.'/toggle-status')->assertStatus(200);
        $this->assertSame('inactive', $country->fresh()->status);
    }

    // ------------------------------------------------------------------- Cities

    public function test_location_manager_can_create_city_under_governorate(): void
    {
        $this->actingAsLocationManager();
        $gov = Govrenrate::factory()->create();

        $response = $this->postJson('/api/v1/admin/cities', [
            'govrenrate_id' => $gov->id,
            'name_ar' => 'مدينة', 'name_en' => 'Town', 'code' => 'TWN-1', 'status' => 'active',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.name_en', 'Town');
        // The city is correctly attached to its governorate (the bug being fixed).
        $this->assertDatabaseHas('cities', ['name_en' => 'Town', 'govrenrate_id' => $gov->id]);
        // And the resource resolves its country through the governorate.
        $this->assertSame($gov->country_id, $response->json('data.country.id'));
    }

    public function test_create_city_requires_governorate(): void
    {
        $this->actingAsLocationManager();

        $this->postJson('/api/v1/admin/cities', ['name_ar' => 'م', 'name_en' => 'X', 'code' => 'X1'])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['govrenrate_id']]);
    }

    public function test_city_code_unique_within_governorate_only(): void
    {
        $this->actingAsLocationManager();
        $govA = Govrenrate::factory()->create();
        $govB = Govrenrate::factory()->create();
        City::factory()->create(['govrenrate_id' => $govA->id, 'code' => 'DUP']);

        // Same code under a different governorate is allowed.
        $this->postJson('/api/v1/admin/cities', [
            'govrenrate_id' => $govB->id, 'name_ar' => 'م', 'name_en' => 'Y', 'code' => 'DUP',
        ])->assertStatus(200);

        // Same code under the same governorate is rejected.
        $this->postJson('/api/v1/admin/cities', [
            'govrenrate_id' => $govA->id, 'name_ar' => 'م', 'name_en' => 'Z', 'code' => 'DUP',
        ])->assertStatus(422)->assertJsonStructure(['params' => ['code']]);
    }

    public function test_location_manager_can_update_and_toggle_city(): void
    {
        $this->actingAsLocationManager();
        $city = City::factory()->create(['name_en' => 'Old', 'status' => 'active']);

        $this->putJson('/api/v1/admin/cities/'.$city->id, [
            'govrenrate_id' => $city->govrenrate_id, 'name_ar' => 'ج', 'name_en' => 'New', 'code' => $city->code,
        ])->assertStatus(200)->assertJsonPath('data.name_en', 'New');

        $this->postJson('/api/v1/admin/cities/'.$city->id.'/toggle-status')->assertStatus(200);
        $this->assertSame('inactive', $city->fresh()->status);
    }

    public function test_admin_can_read_cities_lookup_without_location_permission(): void
    {
        $this->actingAsAdmin(); // read lookups are open to any admin
        City::factory()->count(2)->create();

        $this->getJson('/api/v1/admin/cities')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['items' => [['id', 'name_en', 'country', 'govrenrate_id']]]]);
    }

    // -------------------------------------------------------------------- Areas

    public function test_location_manager_can_create_area_under_city(): void
    {
        $this->actingAsLocationManager();
        $city = City::factory()->create();

        $this->postJson('/api/v1/admin/areas', [
            'city_id' => $city->id, 'name_ar' => 'حي', 'name_en' => 'District', 'code' => 'DST-1',
        ])->assertStatus(200);

        $this->assertDatabaseHas('areas', ['name_en' => 'District', 'city_id' => $city->id]);
    }

    public function test_create_area_requires_city(): void
    {
        $this->actingAsLocationManager();

        $this->postJson('/api/v1/admin/areas', ['name_ar' => 'ح', 'name_en' => 'D', 'code' => 'D1'])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['city_id']]);
    }

    public function test_areas_index_is_paginated_and_filterable_by_city(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        Area::factory()->count(3)->create(['city_id' => $city->id]);
        Area::factory()->count(2)->create(); // other cities

        // Paginated listing.
        $this->getJson('/api/v1/admin/areas')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['items', 'meta']]);

        // City-scoped lookup returns that city's full set (no pagination wrapper meta).
        $this->getJson('/api/v1/admin/areas?city_id='.$city->id)
            ->assertStatus(200)
            ->assertJsonCount(3, 'data.items');
    }

    public function test_location_manager_can_update_and_toggle_area(): void
    {
        $this->actingAsLocationManager();
        $area = Area::factory()->create(['status' => 'active']);

        $this->putJson('/api/v1/admin/areas/'.$area->id, [
            'city_id' => $area->city_id, 'name_ar' => 'ج', 'name_en' => 'New', 'code' => $area->code,
        ])->assertStatus(200);

        $this->postJson('/api/v1/admin/areas/'.$area->id.'/toggle-status')->assertStatus(200);
        $this->assertSame('inactive', $area->fresh()->status);
    }

    // ------------------------------------------------------------- Governorates

    public function test_governorates_lookup_filters_by_country(): void
    {
        $this->actingAsAdmin();
        $country = Country::factory()->create();
        Govrenrate::factory()->count(2)->create(['country_id' => $country->id]);
        Govrenrate::factory()->create(); // another country

        $this->getJson('/api/v1/admin/govrenrates?country_id='.$country->id)
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.items');
    }
}
