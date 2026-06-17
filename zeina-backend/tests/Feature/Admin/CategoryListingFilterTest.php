<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Filters for the admin categories listing: search (name ar/en), status, and
 * type (main = top-level, sub = has a parent).
 */
class CategoryListingFilterTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/categories';

    public function test_search_matches_name(): void
    {
        $this->actingAsAdmin();
        Category::factory()->create(['name_en' => 'Cleaning', 'name_ar' => 'تنظيف']);
        Category::factory()->create(['name_en' => 'Beauty', 'name_ar' => 'تجميل']);

        $this->getJson(self::ENDPOINT.'?search=Cleaning')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name_en', 'Cleaning');
    }

    public function test_filter_by_status(): void
    {
        $this->actingAsAdmin();
        Category::factory()->create(['name_en' => 'ActiveCat', 'status' => 'active']);
        Category::factory()->create(['name_en' => 'InactiveCat', 'status' => 'inactive']);

        $this->getJson(self::ENDPOINT.'?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name_en', 'InactiveCat');
    }

    public function test_filter_by_type_main_returns_only_top_level(): void
    {
        $this->actingAsAdmin();
        $parent = Category::factory()->create(['name_en' => 'Parent']);
        Category::factory()->create(['name_en' => 'Child', 'parent_id' => $parent->id]);

        $response = $this->getJson(self::ENDPOINT.'?type=main')->assertOk();

        $names = collect($response->json('data.items'))->pluck('name_en');
        $this->assertTrue($names->contains('Parent'));
        $this->assertFalse($names->contains('Child'));
    }

    public function test_filter_by_type_sub_returns_only_children(): void
    {
        $this->actingAsAdmin();
        $parent = Category::factory()->create(['name_en' => 'Parent']);
        Category::factory()->create(['name_en' => 'Child', 'parent_id' => $parent->id]);

        $this->getJson(self::ENDPOINT.'?type=sub')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name_en', 'Child');
    }
}
