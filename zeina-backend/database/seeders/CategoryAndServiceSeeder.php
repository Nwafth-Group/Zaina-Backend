<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Service;
use App\Models\City;
use App\Models\Area;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CategoryAndServiceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first(); // لجلب أي مستخدم كـ Creator
        $city = City::first();  // جلب أول مدينة (مثلاً القاهرة)
        $area = Area::where('city_id', $city->id ?? 0)->first(); // جلب منطقة تابعة لها (مثلاً المعادي)

        // 1. إنشاء قسم (Category)
        $cleaningCategory = Category::firstOrCreate(
            ['name_en' => 'Cleaning'], // شرط البحث عشان ما يكرر
            [
                'name_ar' => 'تنظيف',
                'status' => 'active',
                'created_by' => $admin->id ?? null,
            ]
        );

        $babysittingCategory = Category::firstOrCreate(
            ['name_en' => 'Babysitting'],
            [
                'name_ar' => 'جليسة أطفال',
                'status' => 'active',
                'created_by' => $admin->id ?? null,
            ]
        );

        // 2. إنشاء خدمة (Service) تابعة لقسم التنظيف
        $homeCleaningService = Service::firstOrCreate(
            ['name_en' => 'Home Cleaning'],
            [
                'category_id' => $cleaningCategory->id,
                'type' => 'hourly',
                'name_ar' => 'تنظيف منازل',
                'description_ar' => 'خدمة تنظيف منازل احترافية بنظام الساعات.',
                'description_en' => 'Professional hourly home cleaning service.',
                'status' => 'active',
            ]
        );

        // 3. إضافة المدد الزمنية (Durations) للخدمة
        DB::table('service_durations')->updateOrInsert(
            ['service_id' => $homeCleaningService->id],
            [
                'allowed_durations' => json_encode([2, 4, 6, 8]), // تخزين المصفوفة كـ JSON
                'min_duration_hours' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // 4. إضافة القواعد (Rules) للخدمة حسب المثال اللي بشرحك
        DB::table('service_rules')->updateOrInsert(
            ['service_id' => $homeCleaningService->id],
            [
                'free_cancel_cutoff_hours' => 6,
                'reschedule_cutoff_hours' => 4,
                'lead_time_min_hours' => 3,
                'lead_time_max_days' => 14,
                'late_cancel_action' => 'fee',
                'late_cancel_fee_type' => 'fixed',
                'late_cancel_fee_value' => 50.00,
                'no_show_policy' => 'consumed',
                'max_changes_per_booking' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // 5. إضافة التغطية الجغرافية والتسعير (إذا كانت المدن موجودة)
        if ($city) {
            // إضافة التغطية
            DB::table('service_coverages')->updateOrInsert(
                [
                    'service_id' => $homeCleaningService->id, 
                    'city_id' => $city->id, 
                    'area_id' => $area->id ?? null
                ],
                [
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            // إضافة التسعير بناءً على المنطقة
            DB::table('service_prices')->updateOrInsert(
                [
                    'service_id' => $homeCleaningService->id, 
                    'city_id' => $city->id, 
                    'area_id' => $area->id ?? null
                ],
                [
                    'pricing_scope' => $area ? 'area' : 'city',
                    'base_price' => 50.00, // رسوم زيارة ثابتة
                    'price_per_hour' => 80.00, // سعر الساعة
                    'currency' => 'EGP',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }
}