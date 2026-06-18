<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Admin management of customer-app announcements. Routes: apiResource
 * `/api/v1/admin/announcements`, all behind `auth:sanctum` + `IsAdmin`.
 * Announcements are delivered to selected customers via the announcement_user pivot.
 */
class AnnouncementManagementTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/announcements';

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    private function customers(int $count = 3)
    {
        return User::factory()->count($count)->customer()->create();
    }

    public function test_guest_cannot_list_announcements(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_customer_cannot_access_announcements(): void
    {
        $this->actingAsCustomer();

        $this->getJson(self::ENDPOINT)->assertStatus(403);
    }

    public function test_admin_lists_announcements_with_reader_count(): void
    {
        $this->actingAsAdmin();
        $customers = $this->customers(2);
        $announcement = Announcement::factory()->create();
        $announcement->readers()->sync($customers->pluck('id'));

        $this->getJson(self::ENDPOINT)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.readers_count', 2)
            ->assertJsonStructure([
                'data' => ['items' => [['id', 'title', 'body', 'readers_count', 'created_at']]],
            ]);
    }

    public function test_announcements_are_paginated(): void
    {
        $this->actingAsAdmin();
        Announcement::factory()->count(15)->create();

        $this->getJson(self::ENDPOINT.'?per_page=10')
            ->assertStatus(200)
            ->assertJsonCount(10, 'data.items')
            ->assertJsonPath('data.meta.total', 15)
            ->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.last_page', 2);
    }

    public function test_admin_can_create_announcement_for_selected_customers(): void
    {
        $this->actingAsAdmin();
        $customers = $this->customers(3);
        $targets = $customers->take(2)->pluck('id')->all();

        $response = $this->postJson(self::ENDPOINT, [
            'title' => 'Summer sale',
            'body' => 'Up to 40% off all services this week.',
            'customer_ids' => $targets,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Summer sale')
            ->assertJsonPath('data.readers_count', 2);

        $announcement = Announcement::firstWhere('title', 'Summer sale');
        foreach ($targets as $id) {
            $this->assertDatabaseHas('announcement_user', [
                'announcement_id' => $announcement->id,
                'user_id' => $id,
            ]);
        }
    }

    public function test_send_to_all_targets_every_customer(): void
    {
        $this->actingAsAdmin();
        $this->customers(4);

        $this->postJson(self::ENDPOINT, [
            'title' => 'Everyone',
            'body' => 'Broadcast to all customers.',
            'send_to_all' => true,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.readers_count', 4);
    }

    public function test_create_requires_title_and_body(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::ENDPOINT, [])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['title', 'body']]);
    }

    public function test_create_requires_at_least_one_reader(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::ENDPOINT, ['title' => 'Title', 'body' => 'Body'])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['customer_ids']]);
    }

    public function test_create_rejects_non_customer_reader(): void
    {
        $this->actingAsAdmin();
        $admin = User::factory()->superAdmin()->create();

        $this->postJson(self::ENDPOINT, [
            'title' => 'Title',
            'body' => 'Body',
            'customer_ids' => [$admin->id],
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['customer_ids.0']]);
    }

    public function test_admin_can_update_announcement_and_readers(): void
    {
        $this->actingAsAdmin();
        $customers = $this->customers(3);
        $announcement = Announcement::factory()->create(['title' => 'Old', 'body' => 'Old body']);
        $announcement->readers()->sync($customers->take(1)->pluck('id'));

        $newTargets = $customers->skip(1)->pluck('id')->all();

        $this->putJson(self::ENDPOINT.'/'.$announcement->id, [
            'title' => 'New title',
            'body' => 'New body',
            'customer_ids' => $newTargets,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'New title')
            ->assertJsonPath('data.readers_count', 2);

        // Old reader removed, new ones attached.
        $this->assertDatabaseMissing('announcement_user', [
            'announcement_id' => $announcement->id,
            'user_id' => $customers->first()->id,
        ]);
        $this->assertDatabaseHas('announcement_user', [
            'announcement_id' => $announcement->id,
            'user_id' => $newTargets[0],
        ]);
    }

    public function test_show_returns_reader_ids(): void
    {
        $this->actingAsAdmin();
        $customers = $this->customers(2);
        $announcement = Announcement::factory()->create();
        $announcement->readers()->sync($customers->pluck('id'));

        $this->getJson(self::ENDPOINT.'/'.$announcement->id)
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.reader_ids');
    }

    public function test_show_unknown_announcement_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::ENDPOINT.'/99999')->assertStatus(404);
    }
}
