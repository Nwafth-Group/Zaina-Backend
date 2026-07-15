<?php

namespace Tests\Feature\Admin;

use App\Models\PointTransaction;
use App\Models\RewardTasks;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class PointsManagementTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    protected $admin;

    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin and customer users using Zaina helpers
        $this->admin = $this->actingAsAdmin();
        $this->customer = $this->actingAsCustomer(['status' => 'active', 'verification_status' => 'approved']);
    }

    public function test_admin_can_retrieve_earning_rules(): void
    {
        RewardTasks::create([
            'title_ar' => 'تابعنا على إنستغرام',
            'title_en' => 'Follow us on Instagram',
            'points' => 100,
            'rule_category' => 'social_media',
            'frequency_type' => 'one_time',
            'calculation_method' => 'fixed',
            'action_type' => 'url',
            'action_text_ar' => 'تابع',
            'action_text_en' => 'Follow',
            'status' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/points/rules');

        $response->assertOk()
            ->assertJsonPath('data.0.title_en', 'Follow us on Instagram')
            ->assertJsonPath('data.0.points', 100);
    }

    public function test_admin_can_create_earning_rule(): void
    {
        $payload = [
            'title_ar' => 'مكافأة الإحالة',
            'title_en' => 'Referral Reward',
            'points' => 500,
            'rule_category' => 'referral',
            'frequency_type' => 'repeatable',
            'calculation_method' => 'fixed',
            'action_type' => 'url',
            'action_text_ar' => 'شارك',
            'action_text_en' => 'Share',
            'status' => true,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/points/rules', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title_en', 'Referral Reward')
            ->assertJsonPath('data.points', 500);

        $this->assertDatabaseHas('reward_tasks', [
            'title_en' => 'Referral Reward',
            'points' => 500,
        ]);
    }

    public function test_duplicate_rule_name_in_same_category_fails(): void
    {
        RewardTasks::create([
            'title_ar' => 'تابعنا على فيسبوك',
            'title_en' => 'Follow us on Facebook',
            'points' => 100,
            'rule_category' => 'social_media',
            'frequency_type' => 'one_time',
            'calculation_method' => 'fixed',
            'action_type' => 'url',
            'action_text_ar' => 'تابع',
            'action_text_en' => 'Follow',
            'status' => true,
        ]);

        $payload = [
            'title_ar' => 'تابعنا على فيسبوك',
            'title_en' => 'Follow us on Facebook',
            'points' => 200,
            'rule_category' => 'social_media',
            'frequency_type' => 'one_time',
            'calculation_method' => 'fixed',
            'action_type' => 'url',
            'action_text_ar' => 'تابع',
            'action_text_en' => 'Follow',
            'status' => true,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/points/rules', $payload);

        $response->assertStatus(422);
    }

    public function test_admin_cannot_create_social_media_rule_with_repeatable_frequency(): void
    {
        $payload = [
            'title_ar' => 'تابع فيسبوك تكرار',
            'title_en' => 'Follow FB Repeat',
            'points' => 100,
            'rule_category' => 'social_media',
            'frequency_type' => 'repeatable', // Invalid for social_media
            'calculation_method' => 'fixed',
            'status' => true,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/points/rules', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Social media rules must have a "one_time" frequency.');
    }

    public function test_admin_cannot_create_referral_rule_with_onetime_frequency(): void
    {
        $payload = [
            'title_ar' => 'دعوة صديق مرة واحدة',
            'title_en' => 'Invite Friend One Time',
            'points' => 100,
            'rule_category' => 'referral',
            'frequency_type' => 'one_time', // Invalid for referral
            'calculation_method' => 'fixed',
            'status' => true,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/points/rules', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Referral rules must have a "repeatable" frequency.');
    }

    public function test_admin_can_update_rule(): void
    {
        $rule = RewardTasks::create([
            'title_ar' => 'تابعنا على فيسبوك',
            'title_en' => 'Follow us on Facebook',
            'points' => 100,
            'rule_category' => 'social_media',
            'frequency_type' => 'one_time',
            'calculation_method' => 'fixed',
            'action_type' => 'url',
            'action_text_ar' => 'تابع',
            'action_text_en' => 'Follow',
            'status' => true,
        ]);

        $payload = [
            'title_ar' => 'متابعة فيسبوك',
            'title_en' => 'Follow Facebook Page',
            'points' => 150,
            'rule_category' => 'social_media',
            'frequency_type' => 'one_time',
            'calculation_method' => 'fixed',
            'action_type' => 'url',
            'action_text_ar' => 'تابع',
            'action_text_en' => 'Follow',
            'status' => true,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/points/rules/{$rule->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.title_en', 'Follow Facebook Page')
            ->assertJsonPath('data.points', 150);
    }

    public function test_admin_can_delete_rule(): void
    {
        $rule = RewardTasks::create([
            'title_ar' => 'قاعدة مؤقتة',
            'title_en' => 'Temp Rule',
            'points' => 100,
            'rule_category' => 'social_media',
            'frequency_type' => 'one_time',
            'calculation_method' => 'fixed',
            'action_type' => 'url',
            'action_text_ar' => 'تابع',
            'action_text_en' => 'Follow',
            'status' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/points/rules/{$rule->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('reward_tasks', ['id' => $rule->id]);
    }

    public function test_admin_can_manage_service_level_points(): void
    {
        $service = Service::factory()->create([
            'name_ar' => 'خدمة منزلية',
            'name_en' => 'Domestic Help',
            'status' => 'active',
        ]);

        $payload = [
            'points_redemption_enabled' => true,
            'points_redemption_method' => 'price_based',
            'points_required_fixed' => 500,
            'points_conversion_rate' => 15,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/points/services/{$service->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.points_redemption_enabled', true)
            ->assertJsonPath('data.points_redemption_method', 'price_based');
    }

    public function test_admin_can_perform_manual_adjustments(): void
    {
        // 1. Credit points
        $payload = [
            'user_id' => $this->customer->id,
            'action' => 'credit',
            'amount' => 300,
            'reason' => 'Good customer reward',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/points/manual-adjustment', $payload);

        $response->assertOk()
            ->assertJsonPath('data.new_balance', 300);

        $this->assertEquals(300, PointTransaction::getUserPointsBalance($this->customer->id));

        // 2. Debit points with enough balance
        $payload = [
            'user_id' => $this->customer->id,
            'action' => 'debit',
            'amount' => 100,
            'reason' => 'Refund adjustment',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/points/manual-adjustment', $payload);

        $response->assertOk()
            ->assertJsonPath('data.new_balance', 200);

        $this->assertEquals(200, PointTransaction::getUserPointsBalance($this->customer->id));

        // 3. Debit more points than balance (should fail)
        $payload = [
            'user_id' => $this->customer->id,
            'action' => 'debit',
            'amount' => 250,
            'reason' => 'Too high deduction',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/points/manual-adjustment', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient points balance. Customer only has 200 points.');
    }
}
