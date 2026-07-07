<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Filters for the admin services listing: search (name ar/en), status, type,
 * category_id, min_rating, and sort_by/sort_dir.
 */
class ServiceListingFilterTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/services';

    public function test_search_matches_arabic_or_english_name(): void
    {
        $this->actingAsAdmin();
        Service::factory()->create(['name_en' => 'Deep Cleaning', 'name_ar' => 'تنظيف عميق']);
        Service::factory()->create(['name_en' => 'Hair Styling', 'name_ar' => 'تصفيف الشعر']);

        $this->getJson(self::ENDPOINT.'?search=Cleaning')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name_en', 'Deep Cleaning');

        $this->getJson(self::ENDPOINT.'?'.http_build_query(['search' => 'تصفيف']))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name_ar', 'تصفيف الشعر');
    }

    public function test_filter_by_status(): void
    {
        $this->actingAsAdmin();
        Service::factory()->create(['name_en' => 'Active One', 'status' => 'active']);
        Service::factory()->inactive()->create(['name_en' => 'Inactive One']);

        $this->getJson(self::ENDPOINT.'?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name_en', 'Inactive One');
    }

    public function test_filter_by_type(): void
    {
        $this->actingAsAdmin();
        Service::factory()->create(['name_en' => 'Hourly Svc', 'type' => 'hourly']);
        Service::factory()->create(['name_en' => 'Other Svc', 'type' => 'other']);

        $this->getJson(self::ENDPOINT.'?type=other')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name_en', 'Other Svc');
    }

    public function test_filter_by_category(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();
        Service::factory()->create(['name_en' => 'In Category', 'category_id' => $category->id]);
        Service::factory()->create(['name_en' => 'Other Category']);

        $this->getJson(self::ENDPOINT.'?category_id='.$category->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name_en', 'In Category');
    }

    public function test_filter_by_min_rating(): void
    {
        $this->actingAsAdmin();
        Service::factory()->create(['name_en' => 'High Rated', 'avg_rating' => 4.7]);
        Service::factory()->create(['name_en' => 'Low Rated', 'avg_rating' => 2.5]);

        $this->getJson(self::ENDPOINT.'?min_rating=4')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name_en', 'High Rated');
    }

    public function test_filters_combine(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();
        Service::factory()->create(['name_en' => 'Match', 'type' => 'hourly', 'status' => 'active', 'category_id' => $category->id]);
        Service::factory()->create(['name_en' => 'Wrong Type', 'type' => 'other', 'status' => 'active', 'category_id' => $category->id]);
        Service::factory()->inactive()->create(['name_en' => 'Wrong Status', 'type' => 'hourly', 'category_id' => $category->id]);

        $this->getJson(self::ENDPOINT."?type=hourly&status=active&category_id={$category->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name_en', 'Match');
    }

    public function test_sort_by_rating_descending(): void
    {
        $this->actingAsAdmin();
        Service::factory()->create(['name_en' => 'Lower', 'avg_rating' => 3.0]);
        Service::factory()->create(['name_en' => 'Higher', 'avg_rating' => 4.9]);

        $this->getJson(self::ENDPOINT.'?sort_by=avg_rating&sort_dir=desc')
            ->assertOk()
            ->assertJsonPath('data.items.0.name_en', 'Higher');
    }

    public function test_unknown_sort_column_is_ignored(): void
    {
        $this->actingAsAdmin();
        Service::factory()->create(['name_en' => 'Only One']);

        // A bogus sort_by must not error (allow-list falls back to created_at).
        $this->getJson(self::ENDPOINT.'?sort_by=name_en);DROP TABLE services;--&sort_dir=asc')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }
}
