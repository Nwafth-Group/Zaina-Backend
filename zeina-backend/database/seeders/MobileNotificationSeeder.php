<?php

namespace Database\Seeders;

use App\Models\MobileNotification;
use App\Models\User;
use Illuminate\Database\Seeder;

class MobileNotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('type', 'admin')->first() ?? User::first();
        $adminId = $admin ? $admin->id : null;

        // Seed 1: Scheduled Notification
        MobileNotification::factory()->create([
            'title'      => 'عرض الصيف الخاص',
            'title_ar'   => 'خصم 25% على خدمات التجميل',
            'content_ar' => 'لا تفوت فرصة الحصول على خصم 25% عند حجز أي من خدمات التجميل خلال هذا الأسبوع.',
            'title_en'   => 'Summer Special Deal',
            'content_en' => 'Get 25% off all beauty services when booking this week.',
            'audience'   => 'all',
            'status'     => 'scheduled',
            'send_date'  => now()->addDays(5)->format('Y-m-d'),
            'send_time'  => '17:00',
            'platforms'  => ['ios', 'android'],
            'created_by' => $adminId,
        ]);

        // Seed 2: Sent Notification
        MobileNotification::factory()->create([
            'title'      => 'تنبيه تحديث التطبيق للعملاء النشطين',
            'title_ar'   => 'تحديث جديد متوفر للتحميل',
            'content_ar' => 'يرجى تحديث التطبيق الآن للاستمتاع بأحدث الميزات وتحسينات الأداء.',
            'title_en'   => 'New App Version Available',
            'content_en' => 'Please update your application to enjoy the latest features and speed improvements.',
            'audience'   => 'active_customers',
            'status'     => 'sent',
            'send_date'  => now()->subDays(2)->format('Y-m-d'),
            'send_time'  => '09:00',
            'platforms'  => ['android'],
            'created_by' => $adminId,
        ]);

        // Seed 3: Cancelled Notification
        MobileNotification::factory()->create([
            'title'      => 'حملة كوبونات العيد ملغاة',
            'title_ar'   => 'كوبون العيد بانتظارك',
            'content_ar' => 'استخدم الكوبون للحصول على خصم فوري على حجزك القادم.',
            'title_en'   => 'Eid Coupon is Here',
            'content_en' => 'Use coupon to get instant discount on your next booking.',
            'audience'   => 'inactive_customers',
            'status'     => 'cancelled',
            'send_date'  => now()->addDays(1)->format('Y-m-d'),
            'send_time'  => '12:00',
            'platforms'  => ['ios'],
            'created_by' => $adminId,
        ]);
    }
}
