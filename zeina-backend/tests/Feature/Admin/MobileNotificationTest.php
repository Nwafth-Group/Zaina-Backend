<?php

namespace Tests\Feature\Admin;

use App\Models\MobileNotification;
use App\Models\User;
use App\Models\Booking;
use App\Jobs\SendMobileNotificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class MobileNotificationTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/mobile-notifications';

    public function test_guest_cannot_access_mobile_notifications(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_customer_cannot_access_mobile_notifications(): void
    {
        $this->actingAsCustomer();
        $this->getJson(self::ENDPOINT)->assertStatus(403);
    }

    public function test_admin_can_list_mobile_notifications(): void
    {
        $this->actingAsAdmin();

        MobileNotification::factory()->create([
            'title' => 'Internal Label 1',
            'send_date' => '2026-08-01',
            'send_time' => '10:00',
            'platforms' => ['ios'],
        ]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Internal Label 1');
    }

    public function test_admin_can_create_and_schedule_mobile_notification(): void
    {
        Queue::fake();
        $this->actingAsAdmin();

        $tomorrow = now()->addDay()->format('Y-m-d');

        $response = $this->postJson(self::ENDPOINT, [
            'title' => 'Promo Notification',
            'languages' => ['ar', 'en'],
            'title_ar' => 'عرض عربي',
            'content_ar' => 'محتوى عربي',
            'title_en' => 'Promo Title',
            'content_en' => 'Promo Content',
            'audience' => 'all',
            'send_date' => $tomorrow,
            'send_time' => '15:30',
            'platforms' => ['ios', 'android'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Promo Notification')
            ->assertJsonPath('data.status', 'scheduled');

        $notificationId = $response->json('data.id');

        $this->assertDatabaseHas('mobile_notifications', [
            'id' => $notificationId,
            'title' => 'Promo Notification',
            'status' => 'scheduled',
        ]);

        Queue::assertPushed(SendMobileNotificationJob::class);
    }

    public function test_create_rejects_past_scheduled_time(): void
    {
        $this->actingAsAdmin();

        $yesterday = now()->subDay()->format('Y-m-d');

        $response = $this->postJson(self::ENDPOINT, [
            'title' => 'Promo Notification',
            'languages' => ['ar'],
            'title_ar' => 'عرض عربي',
            'content_ar' => 'محتوى عربي',
            'audience' => 'all',
            'send_date' => $yesterday,
            'send_time' => '15:30',
            'platforms' => ['ios'],
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['params' => ['send_time']]);
    }

    public function test_create_rejects_missing_title_for_selected_language(): void
    {
        $this->actingAsAdmin();

        $tomorrow = now()->addDay()->format('Y-m-d');

        // Target language is English, but English title is missing
        $response = $this->postJson(self::ENDPOINT, [
            'title' => 'Promo Notification',
            'languages' => ['en'],
            'title_ar' => 'عربي',
            'content_ar' => 'عربي',
            'audience' => 'all',
            'send_date' => $tomorrow,
            'send_time' => '15:30',
            'platforms' => ['ios'],
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['params' => ['title_en']]);
    }

    public function test_admin_can_update_scheduled_notification(): void
    {
        Queue::fake();
        $this->actingAsAdmin();

        $notification = MobileNotification::factory()->create([
            'status' => 'scheduled',
            'send_date' => now()->addDay()->format('Y-m-d'),
            'send_time' => '10:00',
        ]);

        $tomorrow = now()->addDay()->format('Y-m-d');

        $response = $this->putJson(self::ENDPOINT.'/'.$notification->id, [
            'title' => 'Updated Label',
            'languages' => ['ar'],
            'title_ar' => 'تحديث عربي',
            'content_ar' => 'محتوى تحديث',
            'audience' => 'active_customers',
            'send_date' => $tomorrow,
            'send_time' => '18:00',
            'platforms' => ['android'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mobile_notifications', [
            'id' => $notification->id,
            'title' => 'Updated Label',
            'send_time' => '18:00',
        ]);

        Queue::assertPushed(SendMobileNotificationJob::class);
    }

    public function test_admin_cannot_update_sent_notification(): void
    {
        $this->actingAsAdmin();

        $notification = MobileNotification::factory()->create([
            'status' => 'sent',
        ]);

        $response = $this->putJson(self::ENDPOINT.'/'.$notification->id, [
            'title' => 'Updated Label',
            'languages' => ['ar'],
            'title_ar' => 'تحديث عربي',
            'content_ar' => 'محتوى تحديث',
            'audience' => 'all',
            'send_date' => now()->addDay()->format('Y-m-d'),
            'send_time' => '10:00',
            'platforms' => ['ios'],
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_cancel_notification(): void
    {
        $this->actingAsAdmin();

        $notification = MobileNotification::factory()->create([
            'status' => 'scheduled',
        ]);

        $response = $this->patchJson(self::ENDPOINT.'/'.$notification->id.'/cancel');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $notification->fresh()->status);
    }

    public function test_admin_can_delete_scheduled_or_cancelled_notifications(): void
    {
        $this->actingAsAdmin();

        $scheduled = MobileNotification::factory()->create(['status' => 'scheduled']);
        $cancelled = MobileNotification::factory()->create(['status' => 'cancelled']);

        $this->deleteJson(self::ENDPOINT.'/'.$scheduled->id)->assertStatus(200);
        $this->deleteJson(self::ENDPOINT.'/'.$cancelled->id)->assertStatus(200);

        $this->assertDatabaseMissing('mobile_notifications', ['id' => $scheduled->id]);
        $this->assertDatabaseMissing('mobile_notifications', ['id' => $cancelled->id]);
    }

    public function test_admin_cannot_delete_sent_notifications(): void
    {
        $this->actingAsAdmin();

        $sent = MobileNotification::factory()->create(['status' => 'sent']);

        $this->deleteJson(self::ENDPOINT.'/'.$sent->id)->assertStatus(422);
        $this->assertDatabaseHas('mobile_notifications', ['id' => $sent->id]);
    }
}
