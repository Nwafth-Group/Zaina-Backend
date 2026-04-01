<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ad;
use Carbon\Carbon;

class AdSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $ads = [
            // 1. إعلان فعال وحالياً في فترة العرض (مثالي للصفحة الرئيسية)
            [
                'title' => 'خصم 20% على خدمات التنظيف',
                'description' => 'بمناسبة شهر رمضان، احصل على خصم 20% على جميع خدمات تنظيف المنازل.',
                'start_date' => $now->copy()->subDays(2)->toDateString(), // بدأ قبل يومين
                'end_date' => $now->copy()->addDays(10)->toDateString(),  // بينتهي كمان 10 أيام
                'status' => 'active',
            ],
            // 2. إعلان فعال دائم (بدون تواريخ بداية ونهاية)
            [
                'title' => 'حمل تطبيق زينة الآن!',
                'description' => 'أسرع طريقة لحجز خدماتك المنزلية.',
                'start_date' => null,
                'end_date' => null,
                'status' => 'active',
            ],
            // 3. إعلان فعال ولكن لسا ما بدأ (Future Ad - المفروض ما يظهر للعميل)
            [
                'title' => 'عروض الصيف قريباً',
                'link' => null,
                'description' => 'انتظرونا في أضخم عروض الصيف الأسبوع القادم.',
                'start_date' => $now->copy()->addDays(7)->toDateString(), // بيبدأ كمان أسبوع
                'end_date' => $now->copy()->addDays(20)->toDateString(),
                'status' => 'active',
            ],
            // 4. إعلان منتهي (Expired Ad - المفروض ما يظهر للعميل)
            [
                'title' => 'عرض يوم التأسيس',
                'link' => null,
                'description' => 'انتهى العرض، شكراً لثقتكم.',
                'start_date' => $now->copy()->subDays(10)->toDateString(),
                'end_date' => $now->copy()->subDays(1)->toDateString(), // انتهى امبارح
                'status' => 'active',
            ],
            // 5. إعلان متوقف من الإدارة (Inactive)
            [
                'title' => 'صيانة مؤقتة',
                'link' => null,
                'description' => 'نعتذر، هذه الخدمة متوقفة حالياً للتحديث.',
                'start_date' => $now->copy()->subDays(5)->toDateString(),
                'end_date' => $now->copy()->addDays(5)->toDateString(),
                'status' => 'inactive',
            ],
        ];

        foreach ($ads as $ad) {
            Ad::create($ad);
        }
    }
}