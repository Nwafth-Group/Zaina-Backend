<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Admin management of home banners (ads). Routes: apiResource `/api/v1/admin/ads`
 * + PATCH `{ad}/toggle-status`, all behind `auth:sanctum` + `IsAdmin`.
 */
class BannerManagementTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/ads';

    public function test_guest_cannot_list_banners(): void
    {
        // The ads routes are now behind auth (previously public).
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_customer_cannot_access_banners(): void
    {
        $this->actingAsCustomer();

        $this->getJson(self::ENDPOINT)->assertStatus(403);
    }

    public function test_admin_lists_all_banners_including_inactive(): void
    {
        $this->actingAsAdmin();
        Ad::factory()->count(2)->create();
        Ad::factory()->inactive()->create();

        $this->getJson(self::ENDPOINT)
            ->assertStatus(200)
            ->assertJsonCount(3, 'data.items')
            ->assertJsonStructure([
                'data' => ['items' => [['id', 'link', 'image', 'status']]],
            ]);
    }

    public function test_banners_are_paginated(): void
    {
        $this->actingAsAdmin();
        Ad::factory()->count(15)->create();

        $this->getJson(self::ENDPOINT.'?per_page=10')
            ->assertStatus(200)
            ->assertJsonCount(10, 'data.items')
            ->assertJsonPath('data.meta.total', 15)
            ->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.last_page', 2);
    }

    public function test_admin_can_create_banner_with_image_and_link(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $response = $this->postJson(self::ENDPOINT, [
            'title_en' => 'Test Banner',
            'title_ar' => 'بانر تجريبي',
            'type' => 'general',
            'link_type' => 'external',
            'link' => 'https://zaina.test/offer',
            'status' => 'active',
            'image' => UploadedFile::fake()->image('banner.jpg', 800, 400),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.link', 'https://zaina.test/offer')
            ->assertJsonPath('data.status', 'active');

        $this->assertNotNull($response->json('data.image'));
        $this->assertDatabaseHas('ads', [
            'link' => 'https://zaina.test/offer',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_banner_with_image_only(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $response = $this->postJson(self::ENDPOINT, [
            'title_en' => 'Test Banner',
            'title_ar' => 'بانر تجريبي',
            'type' => 'general',
            'link_type' => 'none',
            'image' => UploadedFile::fake()->image('banner.jpg', 800, 400),
        ]);

        // Link is optional. The response model is un-refreshed so `status` reads
        // null here, but the row persists with the DB default of 'active'.
        $response->assertStatus(201)
            ->assertJsonPath('data.link', null);

        $this->assertNotNull($response->json('data.image'));
        $this->assertDatabaseHas('ads', ['id' => $response->json('data.id'), 'status' => 'active']);
    }

    public function test_admin_can_create_banner_with_link_only(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(self::ENDPOINT, [
            'title_en' => 'Test Banner',
            'title_ar' => 'بانر تجريبي',
            'type' => 'general',
            'link_type' => 'external',
            'link' => 'https://zaina.test/offer',
        ]);

        // Image is optional — a banner can carry just a link.
        $response->assertStatus(201)
            ->assertJsonPath('data.link', 'https://zaina.test/offer')
            ->assertJsonPath('data.image', null);
    }

    public function test_create_rejects_invalid_status(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::ENDPOINT, ['status' => 'archived'])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['status']]);
    }

    public function test_create_rejects_non_image_file(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::ENDPOINT, ['image' => UploadedFile::fake()->create('doc.pdf', 100)])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['image']]);
    }

    public function test_admin_can_update_banner_fields(): void
    {
        $this->actingAsAdmin();
        $ad = Ad::factory()->create(['link' => 'old', 'status' => 'active']);

        $this->putJson(self::ENDPOINT.'/'.$ad->id, [
            'title_en' => 'Updated Banner',
            'title_ar' => 'بانر معدل',
            'type' => 'general',
            'link_type' => 'external',
            'link' => 'new',
            'status' => 'inactive',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.link', 'new')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('ads', ['id' => $ad->id, 'link' => 'new', 'status' => 'inactive']);
    }

    public function test_update_rejects_invalid_status(): void
    {
        $this->actingAsAdmin();
        $ad = Ad::factory()->create();

        $this->putJson(self::ENDPOINT.'/'.$ad->id, [
            'title_en' => 'Test Banner',
            'title_ar' => 'بانر تجريبي',
            'type' => 'general',
            'link_type' => 'external',
            'status' => 'archived',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['status']]);
    }

    public function test_admin_can_toggle_banner_status(): void
    {
        $this->actingAsAdmin();
        $ad = Ad::factory()->create(['status' => 'active']);

        $this->patchJson(self::ENDPOINT.'/'.$ad->id.'/toggle-status')->assertStatus(200);
        $this->assertSame('inactive', $ad->fresh()->status);

        $this->patchJson(self::ENDPOINT.'/'.$ad->id.'/toggle-status')->assertStatus(200);
        $this->assertSame('active', $ad->fresh()->status);
    }

    public function test_toggle_unknown_banner_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->patchJson(self::ENDPOINT.'/99999/toggle-status')->assertStatus(404);
    }

    public function test_admin_can_search_banners_multilingually(): void
    {
        $this->actingAsAdmin();
        $match = Ad::factory()->create(['title_en' => 'UniqueEnglishTitle', 'title_ar' => 'عربي']);
        $other = Ad::factory()->create(['title_en' => 'Other', 'title_ar' => 'عربي']);

        $response = $this->getJson(self::ENDPOINT.'?search=UniqueEnglishTitle');
        $response->assertStatus(200);
        $ids = collect($response->json('data.items'))->pluck('id')->toArray();
        $this->assertContains($match->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_admin_can_filter_banners_by_type_and_status(): void
    {
        $this->actingAsAdmin();
        $coupon = Ad::factory()->create(['type' => 'coupon', 'status' => 'active']);
        $general = Ad::factory()->create(['type' => 'general', 'status' => 'inactive']);

        // Filter by type=coupon
        $response = $this->getJson(self::ENDPOINT.'?type=coupon');
        $response->assertStatus(200);
        $ids = collect($response->json('data.items'))->pluck('id')->toArray();
        $this->assertContains($coupon->id, $ids);
        $this->assertNotContains($general->id, $ids);

        // Filter by status=inactive
        $response = $this->getJson(self::ENDPOINT.'?status=inactive');
        $response->assertStatus(200);
        $ids = collect($response->json('data.items'))->pluck('id')->toArray();
        $this->assertContains($general->id, $ids);
        $this->assertNotContains($coupon->id, $ids);
    }

    public function test_admin_can_sort_banners_by_status(): void
    {
        $this->actingAsAdmin();
        $active = Ad::factory()->create(['status' => 'active']);
        $inactive = Ad::factory()->create(['status' => 'inactive']);

        // Sort by status desc (inactive first because i > a)
        $response = $this->getJson(self::ENDPOINT.'?sort_by=status&sort_dir=desc');
        $response->assertStatus(200);
        $items = $response->json('data.items');
        $this->assertEquals('inactive', $items[0]['status']);
    }

    public function test_admin_can_delete_banner(): void
    {
        $this->actingAsAdmin();
        $ad = Ad::factory()->create();

        $this->deleteJson(self::ENDPOINT.'/'.$ad->id)->assertStatus(200);
        $this->assertSoftDeleted('ads', ['id' => $ad->id]);
    }
}
