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
        // 🌟 1. إضافة مهام كسب النقاط (Reward Tasks) مطابقة لتصميم الفيجما
        $tasks = [
            [
                'title_en'       => 'Follow us on Facebook',
                'title_ar'       => 'تابعنا على فيسبوك',
                'subtitle_en'    => 'Stay updated with our latest offers',
                'subtitle_ar'    => 'ابق على اطلاع بآخر العروض',
                'points'         => 10,
                'action_text_en' => 'Follow',
                'action_text_ar' => 'متابعة',
                'action_type'    => 'url',
                'action_link'    => 'https://facebook.com/your_page',
                'status'         => true,
            ],
            [
                'title_en'       => 'Follow us on Instagram',
                'title_ar'       => 'تابعنا على انستغرام',
                'subtitle_en'    => 'Get tips and offers',
                'subtitle_ar'    => 'احصل على نصائح وعروض',
                'points'         => 10,
                'action_text_en' => 'Follow',
                'action_text_ar' => 'متابعة',
                'action_type'    => 'url',
                'action_link'    => 'https://instagram.com/your_page',
                'status'         => true,
            ],
            [
                'title_en'       => 'Invite a friend',
                'title_ar'       => 'دعوة صديق',
                'subtitle_en'    => 'Earn rewards when they sign up',
                'subtitle_ar'    => 'اكسب مكافآت عند تسجيلهم',
                'points'         => 10,
                'action_text_en' => 'Invite',
                'action_text_ar' => 'دعوة',
                'action_type'    => 'invite_friend',
                'action_link'    => null,
                'status'         => true,
            ],
            [
                'title_en'       => 'Rate a service',
                'title_ar'       => 'تقييم خدمة',
                'subtitle_en'    => 'Rate your completed service to earn points',
                'subtitle_ar'    => 'قيم خدمتك المكتملة لكسب النقاط',
                'points'         => 5,
                'action_text_en' => 'Rate Now',
                'action_text_ar' => 'قيّم الآن',
                'action_type'    => 'rate_service',
                'action_link'    => null,
                'status'         => true,
            ]
        ];

        foreach ($tasks as $taskData) {
            RewardTasks::firstOrCreate(
                ['title_en' => $taskData['title_en']], // لتجنب التكرار إذا شغلنا السيدر مرتين
                $taskData
            );
        }
        // 🌟 2. إنشاء حركات وهمية (History) لأول 3 عملاء عشان التيست
        $customers = User::where('type', 'customer')->get();
        $allTasks = RewardTasks::all();
        foreach ($customers as $customer) {
            // حركة رقم 1: كسب نقاط من خدمة (تاريخ اليوم)
            PointTransaction::create([
                'user_id'  => $customer->id,
                'type'     => 'earn',
                'amount'   => 100,
                'title_ar' => 'إكمال خدمة تنظيف',
                'title_en' => 'Completed Cleaning Service',
                'created_at' => Carbon::now(),
            ]);
            // حركة رقم 2: خصم نقاط (استبدال بمحفظة) - (تاريخ قبل 3 أيام عشان تيست الفلاتر)
            PointTransaction::create([
                'user_id'  => $customer->id,
                'type'     => 'redeem',
                'amount'   => 20,
                'title_ar' => 'استبدال النقاط بخصم',
                'title_en' => 'Redeem points for discount',
                'created_at' => Carbon::now()->subDays(3),
            ]);
            // حركة رقم 3: كسب نقاط من متابعة فيسبوك - (تاريخ قبل 10 أيام)
            $fbTask = $allTasks->where('action_type', 'url')->first();
            if ($fbTask) {
                PointTransaction::create([
                    'user_id'  => $customer->id,
                    'type'     => 'earn',
                    'amount'   => $fbTask->points,
                    'title_ar' => 'مكافأة: ' . $fbTask->title_ar,
                    'title_en' => 'Reward: ' . $fbTask->title_en,
                    'created_at' => Carbon::now()->subDays(10),
                ]);
                // 🌟 3. تسجيل أن هذا العميل أتم مهمة الفيسبوك (عشان الزر يصير Disable)
                DB::table('user_reward_tasks')->insertOrIgnore([
                    'user_id'    => $customer->id,
                    'task_id'    => $fbTask->id,
                    'created_at' => Carbon::now()->subDays(10),
                    'updated_at' => Carbon::now()->subDays(10),
                ]);
            }
        }
    }
}