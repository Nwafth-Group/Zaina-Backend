<?php

namespace Tests\Feature\Customer;

use App\Models\Booking;
use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\ServiceRule;
use App\Models\Worker;
use App\Models\RewardTasks;
use App\Models\PointTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyPointsFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Notification::fake();
        // Seed points settings if needed
        \Illuminate\Support\Facades\DB::table('point_settings')->updateOrInsert(
            ['key' => 'points_conversion_rate'],
            ['value' => '10', 'updated_at' => now()]
        );
        \Illuminate\Support\Facades\DB::table('point_settings')->updateOrInsert(
            ['key' => 'service_cycle_rules'],
            ['value' => json_encode(['enabled' => true]), 'target_steps' => 3, 'reward_points' => 100, 'action_type' => 'complete_bookings', 'updated_at' => now()]
        );
    }

    private function actingAsCustomer()
    {
        $user = User::factory()->create(['type' => 'customer', 'status' => 'active', 'verification_status' => 'approved']);
        $this->actingAs($user, 'sanctum');
        return $user;
    }

    public function test_customer_can_view_points_summary_and_referral_stats(): void
    {
        $user = $this->actingAsCustomer();

        // Seed active rules
        RewardTasks::create([
            'title_ar' => 'دعوة صديق',
            'title_en' => 'Invite Friend',
            'points' => 150,
            'action_type' => 'invite_friend',
            'action_text_ar' => 'دعوة',
            'action_text_en' => 'Invite',
            'status' => true,
            'rule_category' => 'referral',
            'trigger_event' => 'referred_register',
            'frequency_type' => 'repeatable',
            'calculation_method' => 'fixed',
        ]);

        RewardTasks::create([
            'title_ar' => 'إتمام حجز',
            'title_en' => 'Complete Booking',
            'points' => 20,
            'action_type' => 'rate_service',
            'action_text_ar' => 'حجز',
            'action_text_en' => 'Book',
            'status' => true,
            'rule_category' => 'booking',
            'trigger_event' => 'booking_completed',
            'frequency_type' => 'repeatable',
            'calculation_method' => 'fixed',
        ]);

        // Credit some points
        $user->pointTransactions()->create([
            'type' => 'earn',
            'amount' => 150,
            'title_ar' => 'Test Earn',
            'title_en' => 'Test Earn',
        ]);

        $response = $this->getJson('/api/v1/customer/points/summary');

        $response->assertOk()
            ->assertJsonPath('data.total_points', 150)
            ->assertJsonPath('data.referral_code', $user->referral_code)
            ->assertJsonCount(3, 'data.progress_card')
            ->assertJsonPath('data.progress_card.0.action_type', 'invite_friend')
            ->assertJsonPath('data.progress_card.1.action_type', 'complete_booking')
            ->assertJsonPath('data.progress_card.2.action_type', 'complete_bookings');
    }

    public function test_customer_earn_points_category_filtering(): void
    {
        $user = $this->actingAsCustomer();

        RewardTasks::create([
            'title_ar' => 'فيس بوك',
            'title_en' => 'Facebook',
            'points' => 10,
            'rule_category' => 'social_media',
            'status' => true,
            'frequency_type' => 'one_time',
            'calculation_method' => 'fixed',
            'action_type' => 'url',
            'action_text_ar' => 'متابعة',
            'action_text_en' => 'Follow',
        ]);

        RewardTasks::create([
            'title_ar' => 'احالة',
            'title_en' => 'Referral',
            'points' => 50,
            'rule_category' => 'referral',
            'status' => true,
            'frequency_type' => 'repeatable',
            'calculation_method' => 'fixed',
            'action_type' => 'url',
            'action_text_ar' => 'دعوة',
            'action_text_en' => 'Invite',
        ]);

        // Filter for social_media
        $response = $this->getJson('/api/v1/customer/points/earn-points?category=social_media');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('social_media', $response->json('data.0.rule_category'));

        // Filter for referral
        $response = $this->getJson('/api/v1/customer/points/earn-points?category=referral');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('referral', $response->json('data.0.rule_category'));
    }

    public function test_customer_completing_one_time_task_versus_repeatable(): void
    {
        $user = $this->actingAsCustomer();
        
        $taskOneTime = RewardTasks::create([
            'title_ar' => 'تابعنا على فيسبوك',
            'title_en' => 'Follow us on Facebook',
            'points' => 50,
            'action_text_ar' => 'متابعة',
            'action_text_en' => 'Follow',
            'action_type' => 'url',
            'action_link' => 'https://facebook.com',
            'status' => true,
        ]);

        // Submit one time
        $this->postJson('/api/v1/customer/points/complete-task', ['task_id' => $taskOneTime->id])
            ->assertOk();

        $this->assertEquals(50, PointTransaction::getUserPointsBalance($user->id));

        // Submit again - should be rejected for one-time URL type
        $this->postJson('/api/v1/customer/points/complete-task', ['task_id' => $taskOneTime->id])
            ->assertStatus(422);
    }

    public function test_checkout_pricing_returns_loyalty_points_metadata(): void
    {
        $user = $this->actingAsCustomer();
        $city = \App\Models\City::factory()->create(['status' => 'active']);
        $area = \App\Models\Area::factory()->create(['city_id' => $city->id, 'status' => 'active']);
        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
        ]);

        $service = Service::factory()->create([
            'status' => 'active',
            'base_price' => 100.00,
            'points_redemption_enabled' => true,
            'points_redemption_method' => 'ratio',
            'points_conversion_rate' => 5,
        ]);

        \App\Models\ServiceCoverage::create([
            'service_id' => $service->id,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'status' => 'active',
        ]);

        $worker = Worker::factory()->create(['status' => 'active']);

        $response = $this->postJson('/api/v1/customer/bookings/validate', [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'worker_ids' => [$worker->id],
            'dates' => [Carbon::now()->addDays(2)->toDateString()],
            'start_time' => '10:00',
            'duration' => 2,
            'visit_type' => 'single_visit',
            'workers_count' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.pricing.loyalty_points.redemption_enabled', true)
            ->assertJsonPath('data.pricing.loyalty_points.redemption_method', 'ratio')
            ->assertJsonStructure(['data' => ['pricing' => ['loyalty_points' => ['points_required']]]]);
    }

    public function test_customer_points_redemption_during_checkout(): void
    {
        $user = $this->actingAsCustomer();
        $city = \App\Models\City::factory()->create(['status' => 'active']);
        $area = \App\Models\Area::factory()->create(['city_id' => $city->id, 'status' => 'active']);
        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
        ]);
        
        // Setup service points parameters
        $service = Service::factory()->create([
            'status' => 'active',
            'base_price' => 100.00,
            'points_redemption_enabled' => true,
            'points_redemption_method' => 'fixed',
            'points_required_fixed' => 200,
        ]);

        \App\Models\ServiceCoverage::create([
            'service_id' => $service->id,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'status' => 'active',
        ]);
        
        $worker = Worker::factory()->create(['status' => 'active']);

        // Attempt booking payment with points - should fail since balance is 0
        $this->postJson('/api/v1/customer/bookings', [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'worker_ids' => [$worker->id],
            'dates' => [Carbon::now()->addDays(2)->toDateString()],
            'start_time' => '10:00',
            'duration' => 2,
            'visit_type' => 'single_visit',
            'payment_method' => 'points',
            'workers_count' => 1,
        ])->assertStatus(422);

        // Credit 300 points
        $user->pointTransactions()->create([
            'type' => 'earn',
            'amount' => 300,
            'title_ar' => 'Initial',
            'title_en' => 'Initial',
        ]);

        // Booking payment with points should succeed now
        $response = $this->postJson('/api/v1/customer/bookings', [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'worker_ids' => [$worker->id],
            'dates' => [Carbon::now()->addDays(2)->toDateString()],
            'start_time' => '10:00',
            'duration' => 2,
            'visit_type' => 'single_visit',
            'payment_method' => 'points',
            'workers_count' => 1,
        ]);

        $response->assertCreated();
        $bookingId = $response->json('data.id');

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'payment_method' => 'points',
            'paid_points' => 200,
        ]);

        $this->assertEquals(100, PointTransaction::getUserPointsBalance($user->id));
    }

    public function test_friend_referral_rewards_referrer_on_first_booking_completion(): void
    {
        // 1. Create referrer
        $referrer = User::factory()->create(['type' => 'customer', 'status' => 'active', 'verification_status' => 'approved']);
        
        // 2. Setup referral tasks rules
        RewardTasks::create([
            'title_ar' => 'دعوة صديق',
            'title_en' => 'Invite Friend',
            'points' => 150,
            'action_text_ar' => 'دعوة',
            'action_text_en' => 'Invite',
            'action_type' => 'invite_friend',
            'status' => true,
            'rule_category' => 'referral',
            'trigger_event' => 'referred_register',
        ]);

        // 3. Register friend using referrer code
        $friend = User::factory()->create([
            'type' => 'customer',
            'status' => 'active',
            'verification_status' => 'approved',
            'referred_by_id' => $referrer->id,
        ]);

        // Referrer points should remain 0 initially
        $this->assertEquals(0, PointTransaction::getUserPointsBalance($referrer->id));

        // 4. Friend makes a booking and is completed
        $service = Service::factory()->create(['status' => 'active']);
        $address = CustomerAddress::factory()->create(['user_id' => $friend->id]);
        $booking = Booking::create([
            'booking_ref' => 'REF-BK-999',
            'customer_id' => $friend->id,
            'service_id' => $service->id,
            'address_id' => $address->id,
            'booking_date' => Carbon::now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'duration_minutes' => 120,
            'status' => 'completed',
            'price_subtotal' => 100,
            'total_amount' => 100,
        ]);

        // Complete the booking via status update logic (which triggers awardPointsForCompletedBooking)
        \App\Service\LoyaltyPointService::awardPointsForCompletedBooking($booking);

        // Referrer should now receive the 150 points!
        $this->assertEquals(150, PointTransaction::getUserPointsBalance($referrer->id));
        
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $referrer->id,
            'type' => 'earn',
            'amount' => 150,
            'reason' => 'friend_first_booking',
        ]);
    }
}
