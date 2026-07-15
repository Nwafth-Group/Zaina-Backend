<?php

namespace Database\Seeders;

use App\Models\PointTransaction;
use App\Models\RewardTasks;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PointSystemSeeder extends Seeder
{
    public function run(): void
    {
        // 🌟 1. إدخال قواعد شريط التقدم الافتراضية - وضع الحقل الفريد في المصفوفة الأولى 🌟
        DB::table('point_settings')->updateOrInsert(
                ['key' => 'service_cycle_rules'], // مصفوفة البحث والتحقق من الـ Unique
                [
                    'action_type'   => 'complete_bookings',
                    'target_steps'  => 3,
                    'reward_points' => 100,
                    'updated_at'    => now()
                ]
            );

        // 🌟 2. إدخال مهام كسب النقاط الديناميكية (Reward Tasks) 🌟
        $tasks = [
            [
                'title_ar'       => 'متابعة صفحتنا على فيسبوك',
                'title_en'       => 'Follow us on Facebook',
                'subtitle_ar'    => 'احصل على نقاط فورية بمجرد المتابعة',
                'subtitle_en'    => 'Get instant points for following our page',
                'points'         => 50,
                'action_type'    => 'url',
                'action_text_ar' => 'تابعنا',
                'action_text_en' => 'Follow',
                'action_link'    => 'https://facebook.com/zeina.app', // 🌟 هذا الفريد للمهمة الأولى
                'status'         => true,
            ],
            [
                'title_ar'       => 'متابعة حسابنا على إنستغرام',
                'title_en'       => 'Follow us on Instagram',
                'subtitle_ar'    => 'ابق على اطلاع بأحدث عروضنا وتجميلنا',
                'subtitle_en'    => 'Stay updated with our latest lifestyle posts',
                'points'         => 40,
                'action_type'    => 'url',
                'action_text_ar' => 'تابعنا',
                'action_text_en' => 'Follow',
                'action_link'    => 'https://instagram.com/zeina.app', // 🌟 هذا الفريد للمهمة الثانية
                'status'         => true,
            ],
            [
                'title_ar'       => 'مشاركة التطبيق مع الأصدقاء',
                'title_en'       => 'Share App with Friends',
                'subtitle_ar'    => 'انشر الحب واكسب نقاط إضافية',
                'subtitle_en' => 'Spread the love and earn bonus points',
                'points'         => 75,
                'action_type'    => 'url',
                'action_text_ar' => 'شارك التطبيق',
                'action_text_en' => 'Share App',
                'action_link'    => 'https://zeina.com/share', // 🌟 هذا الفريد للمهمة الثالثة
                'status'         => true,
            ]
        ];

        foreach ($tasks as $task) {
            // 🌟 التعديل الجوهري والسحري هنا 🌟
            // نبحث بالـ action_link لأنه فريد ومستحيل يتكرر بين المهمات، والـ url يوضع بالتحديث عادي
            DB::table('reward_tasks')->updateOrInsert(
                ['action_link' => $task['action_link']], // ابحث بالرابط لضمان عدم الدمج والتكرار
                array_merge($task, ['updated_at' => now()])
            );
        }

        // 🌟 3. حقن رصيد نقاط تجريبي ضخم (500 Pts) لكل المستخدمين للتطوير 🌟
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found in database. Please run UserSeeder before running LoyaltyPointsSeeder.');
            return;
        }

        foreach ($users as $user) {
            $hasWelcomePoints = DB::table('point_transactions')
                ->where('user_id', $user->id)
                ->where('title_en', 'Development Mock Bonus Points')
                ->exists();

            if (!$hasWelcomePoints) {
                DB::table('point_transactions')->insert([
                    'user_id'    => $user->id,
                    'type'       => 'earn',
                    'amount'     => 500,
                    'title_ar'   => 'نقاط ترحيبية مخصصة لبيئة التطوير والفحص',
                    'title_en'   => 'Development Mock Bonus Points',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        $this->command->info('Loyalty points system configuration and user balances seeded successfully!');
    }
}
