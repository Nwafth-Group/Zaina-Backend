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
            ->assertJsonPath('data.referral_code', $user->activeReferralCode())
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

    public function test_friend_referral_rewards_referrer_on_kyc_approval(): void
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

        // 3. Register friend using referrer code (not approved yet)
        $friend = User::factory()->create([
            'type' => 'customer',
            'status' => 'active',
            'verification_status' => 'pending',
            'referred_by_id' => $referrer->id,
        ]);

        // Referrer points should remain 0 initially
        $this->assertEquals(0, PointTransaction::getUserPointsBalance($referrer->id));

        // 4. Approve the friend (simulating KYC approval)
        $friend->verification_status = 'approved';
        $friend->save();

        \App\Service\LoyaltyPointService::awardPointsForReferralApproval($friend);

        // Referrer should now receive the 150 points!
        $this->assertEquals(150, PointTransaction::getUserPointsBalance($referrer->id));
        
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $referrer->id,
            'type' => 'earn',
            'amount' => 150,
            'reason' => 'referral_approved',
        ]);
    }

    public function test_customer_can_generate_new_referral_code_with_limit(): void
    {
        $user = $this->actingAsCustomer();

        // 1. First 3 generations should succeed
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/v1/customer/points/generate-referral-code');
            $response->assertOk()
                ->assertJsonStructure(['success', 'data' => ['referral_code', 'expires_at']]);
        }

        // 2. 4th generation should fail with 422 limit exceeded
        $response = $this->postJson('/api/v1/customer/points/generate-referral-code');
        $response->assertStatus(422);
    }

    public function test_expired_referral_code_fails_kyc_submission(): void
    {
        $user = $this->actingAsCustomer();
        $referrer = User::factory()->create(['type' => 'customer', 'status' => 'active']);

        // Create an expired referral code
        $expiredCode = \App\Models\UserReferralCode::create([
            'user_id' => $referrer->id,
            'code' => 'REF-EXPIRED',
            'expires_at' => now()->subMinutes(10),
        ]);

        // Upload documents and try to verify with expired code
        $idFront = \Illuminate\Http\UploadedFile::fake()->image('front.jpg');
        $idBack = \Illuminate\Http\UploadedFile::fake()->image('back.jpg');
        $selfie = \Illuminate\Http\UploadedFile::fake()->image('selfie.jpg');

        $response = $this->postJson('/api/v1/customer/verify', [
            'id_front' => $idFront,
            'id_back' => $idBack,
            'selfie' => $selfie,
            'referral_code' => 'REF-EXPIRED',
        ]);

        // Assert validation fails
        $response->assertStatus(422)
            ->assertJsonValidationErrors('referral_code');
    }

    public function test_expired_referral_code_cleanup_command(): void
    {
        $user = User::factory()->create(['type' => 'customer']);

        // 1. Active referral code
        $activeCode = \App\Models\UserReferralCode::create([
            'user_id' => $user->id,
            'code' => 'REF-ACTIVE',
            'expires_at' => now()->addDays(2),
        ]);

        // 2. Expired referral code
        $expiredCode = \App\Models\UserReferralCode::create([
            'user_id' => $user->id,
            'code' => 'REF-DEAD',
            'expires_at' => now()->subDay(),
        ]);

        // Run cleanup command
        $this->artisan('points:cleanup-expired-referrals');

        // Expired code should be deleted, active code should remain
        $this->assertDatabaseMissing('user_referral_codes', ['code' => 'REF-DEAD']);
        $this->assertDatabaseHas('user_referral_codes', ['code' => 'REF-ACTIVE']);
    }
}
