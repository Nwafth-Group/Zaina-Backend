<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerServiceListingFilterTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/customer/services';

    public function test_customer_can_list_all_active_services(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        Service::factory()->count(3)->create([
            'category_id' => $category->id,
            'status' => 'active'
        ]);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(3, 'data.items');
    }

    public function test_customer_can_filter_services_by_type(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        Service::factory()->create([
            'name_en' => 'Hourly Service',
            'category_id' => $category->id,
            'type' => 'hourly',
            'status' => 'active'
        ]);
        Service::factory()->create([
            'name_en' => 'Other Service',
            'category_id' => $category->id,
            'type' => 'other',
            'status' => 'active'
        ]);

        // Filter by hourly
        $this->getJson(self::ENDPOINT . '?type=hourly')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name_en', 'Hourly Service');

        // Filter by other
        $this->getJson(self::ENDPOINT . '?type=other')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name_en', 'Other Service');
    }
}
