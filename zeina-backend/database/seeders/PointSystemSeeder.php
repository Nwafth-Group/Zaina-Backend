<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PointSystemSeeder extends Seeder
{
    public function run(): void
    {
        // 🌟 1. Seed global progress cycle & redemption settings
        $settings = [
            'service_cycle_rules' => [
                'action_type' => 'complete_bookings',
                'target_steps' => 3,
                'reward_points' => 100,
                'value' => '3',
            ],
        ];

        foreach ($settings as $key => $values) {
            DB::table('point_settings')->updateOrInsert(
                ['key' => $key],
                array_merge($values, ['updated_at' => now()])
            );
        }

        // 🌟 2. Seed default points earning rules (Reward Tasks)
        $tasks = [
            [
                'title_ar' => 'متابعة صفحتنا على فيسبوك',
                'title_en' => 'Follow us on Facebook',
                'subtitle_ar' => 'احصل على نقاط فورية بمجرد المتابعة',
                'subtitle_en' => 'Get instant points for following our page',
                'points' => 50,
                'action_type' => 'url',
                'action_text_ar' => 'تابعنا',
                'action_text_en' => 'Follow',
                'action_link' => 'https://facebook.com/zeina.app',
                'status' => true,
                'rule_category' => 'social_media',
                'trigger_event' => 'facebook_follow',
                'frequency_type' => 'one_time',
                'calculation_method' => 'fixed',
                'points_expiration_months' => 12,
            ],
            [
                'title_ar' => 'متابعة حسابنا على إنستغرام',
                'title_en' => 'Follow us on Instagram',
                'subtitle_ar' => 'ابق على اطلاع بأحدث عروضنا وتجميلنا',
                'subtitle_en' => 'Stay updated with our latest lifestyle posts',
                'points' => 40,
                'action_type' => 'url',
                'action_text_ar' => 'تابعنا',
                'action_text_en' => 'Follow',
                'action_link' => 'https://instagram.com/zeina.app',
                'status' => true,
                'rule_category' => 'social_media',
                'trigger_event' => 'instagram_follow',
                'frequency_type' => 'one_time',
                'calculation_method' => 'fixed',
                'points_expiration_months' => 12,
            ],
            [
                'title_ar' => 'مشاركة التطبيق مع الأصدقاء',
                'title_en' => 'Share App with Friends',
                'subtitle_ar' => 'انشر الحب واكسب نقاط إضافية',
                'subtitle_en' => 'Spread the love and earn bonus points',
                'points' => 75,
                'action_type' => 'url',
                'action_text_ar' => 'شارك التطبيق',
                'action_text_en' => 'Share App',
                'action_link' => 'https://zeina.com/share',
                'status' => true,
                'rule_category' => 'social_media',
                'trigger_event' => 'app_share',
                'frequency_type' => 'repeatable',
                'calculation_method' => 'fixed',
                'points_expiration_months' => 12,
            ],
            [
                'title_ar' => 'مكافأة دعوة صديق',
                'title_en' => 'Referral Reward',
                'subtitle_ar' => 'اكسب نقاط عند تسجيل صديق باستخدام كود الإحالة الخاص بك',
                'subtitle_en' => 'Earn points when a friend registers using your referral code',
                'points' => 150,
                'action_type' => 'invite_friend',
                'action_text_ar' => 'دعوة الأصدقاء',
                'action_text_en' => 'Invite Friends',
                'action_link' => 'invite_friend',
                'status' => true,
                'rule_category' => 'referral',
                'trigger_event' => 'referred_register',
                'frequency_type' => 'repeatable',
                'calculation_method' => 'fixed',
                'points_expiration_months' => 12,
            ],
            [
                'title_ar' => 'مكافأة إتمام الخدمة',
                'title_en' => 'Booking Completed Reward',
                'subtitle_ar' => 'اكسب نقاط إضافية على كل حجز مكتمل',
                'subtitle_en' => 'Earn bonus points on every completed booking',
                'points' => 20,
                'action_type' => 'url',
                'action_text_ar' => 'احجز الآن',
                'action_text_en' => 'Book Now',
                'action_link' => 'booking_completed',
                'status' => true,
                'rule_category' => 'booking',
                'trigger_event' => 'booking_completed',
                'frequency_type' => 'repeatable',
                'calculation_method' => 'fixed',
                'points_expiration_months' => 12,
            ],
        ];

        foreach ($tasks as $task) {
            DB::table('reward_tasks')->updateOrInsert(
                ['action_link' => $task['action_link']],
                array_merge($task, ['updated_at' => now()])
            );
        }

        // 🌟 3. Inject mock balance history to dev users for testing
        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            $hasWelcomePoints = DB::table('point_transactions')
                ->where('user_id', $user->id)
                ->where('title_en', 'Development Mock Bonus Points')
                ->exists();

            if (! $hasWelcomePoints) {
                DB::table('point_transactions')->insert([
                    'user_id' => $user->id,
                    'type' => 'earn',
                    'amount' => 500,
                    'title_ar' => 'نقاط ترحيبية مخصصة لبيئة التطوير والفحص',
                    'title_en' => 'Development Mock Bonus Points',
                    'points_before' => 0,
                    'points_after' => 500,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
