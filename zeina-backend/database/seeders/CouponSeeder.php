<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        // 1. جلب عينات من البيانات لربط الكوبونات المخصصة
        $customers = User::where('type', 'customer')->take(2)->get();
        
        // جلب خدمات التنظيف (التي تبدأ بـ Regular أو Deep أو Post-Construction) لتخصيص كوبون لها
        $cleaningServices = Service::where('name_en', 'like', '%Cleaning%')->pluck('id')->toArray();
        $allServices = Service::pluck('id')->toArray();

        if ($customers->isEmpty() || empty($allServices)) {
            $this->command->error('يجب تشغيل UserSeeder و CategoryAndServiceSeeder أولاً قبل تشغيل كوبونات النظام!');
            return;
        }

        // =========================================================================
        // السيناريو 1: كوبون عام ومفتوح لجميع المستخدمين وكل الخدمات بقيمة ثابتة
        // ينتهي بعد شهر والحد الأدنى للحجز 200 جنيه، يخصم 50 جنيه ثابتة
        // =========================================================================
        $globalCoupon = Coupon::firstOrCreate(
            ['code' => 'WELCOME50'],
            [
                'type' => 'fixed',
                'value' => 50.00,
                'max_discount' => null,
                'min_booking_amount' => 200.00,
                'usage_limit' => 1000, // متاح لأول 1000 مستخدم في التطبيق
                'user_usage_limit' => 1, // كل كستمر يستعمله مرة واحدة فقط
                'used_count' => 0,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(1),
                'is_for_all_users' => true,
                'is_for_all_services' => true,
                'status' => 'active',
            ]
        );

         $globalCoupon2 = Coupon::firstOrCreate(
            ['code' => 'GLOBALCOUPON2'],
            [
                'type' => 'percentage',
                'value' => 50.00,
                'max_discount' => null,
                'min_booking_amount' => 50.00,
                'usage_limit' => 1000, // متاح لأول 1000 مستخدم في التطبيق
                'user_usage_limit' => 1, // كل كستمر يستعمله مرة واحدة فقط
                'used_count' => 0,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(1),
                'is_for_all_users' => true,
                'is_for_all_services' => true,
                'status' => 'active',
            ]
        );

        // =========================================================================
        // السيناريو 2: كوبون VIP مخصص "فقط" لمستخدمين معينين (ممنوع لغيرهم)
        // يعطي خصم ضخم 150 جنيه ثابتة، ويشمل كل الخدمات في التطبيق
        // =========================================================================
        $vipCoupon = Coupon::firstOrCreate(
            ['code' => 'AHMADVIP'],
            [
                'type' => 'fixed',
                'value' => 150.00,
                'max_discount' => null,
                'min_booking_amount' => 300.00,
                'usage_limit' => 50,
                'user_usage_limit' => 2, // مسموح للمستخدم المخصص استعماله مرتين
                'used_count' => 0,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addWeeks(2),
                'is_for_all_users' => false, // 🌟 مخصص وليس عام
                'is_for_all_services' => true,
                'status' => 'active',
            ]
        );
        // ربط الكوبون بالمستخدمين الذين جلبناهم في البداية
        $vipCoupon->users()->sync($customers->pluck('id')->toArray());
       ;


        // =========================================================================
        // السيناريو 3: كوبون مخصص "فقط" لخدمات التنظيف (شامل كل المستخدمين)
        // بنسبة مئوية (20%) مع سقف خصم (Max Discount) يساوي 80 جنيه
        // =========================================================================
        if (!empty($cleaningServices)) {
            $cleanCoupon = Coupon::firstOrCreate(
                ['code' => 'CLEAN20'],
                [
                    'type' => 'percentage',
                    'value' => 20.00, // 20%
                    'max_discount' => 80.00, // 🌟 أقصى قيمة خصم مسموحة هي 80 جنيه
                    'min_booking_amount' => 100.00,
                    'usage_limit' => 500,
                    'user_usage_limit' => 1,
                    'used_count' => 0,
                    'start_date' => Carbon::now(),
                    'end_date' => Carbon::now()->addDays(10),
                    'is_for_all_users' => true,
                    'is_for_all_services' => false, // 🌟 مخصص لخدمات معينة وليس الكل
                    'status' => 'active',
                ]
            );
            // ربط الكوبون بخدمات التنظيف فقط في الجدول الوسيط
            $cleanCoupon->services()->sync($cleaningServices);
        }


        // =========================================================================
        // السيناريو 4: كوبون منتهي الصلاحية (Expired Coupon)
        // مخصص لفحص شروط الحماية في الـ Validation للتأكد أن النظام يرفضه تلقائياً
        // =========================================================================
        Coupon::firstOrCreate(
            ['code' => 'EXPIRED10'],
            [
                'type' => 'percentage',
                'value' => 10.00,
                'max_discount' => 30.00,
                'min_booking_amount' => 50.00,
                'usage_limit' => 100,
                'user_usage_limit' => 1,
                'used_count' => 0,
                'start_date' => Carbon::now()->subMonths(2), // بدأ قبل شهرين
                'end_date' => Carbon::now()->subMonth(),    // انتهى قبل شهر ❌
                'is_for_all_users' => true,
                'is_for_all_services' => true,
                'status' => 'active',
            ]
        );


        // =========================================================================
        // السيناريو 5: كوبون مكثف (مخصص لمستخدم معين + خدمة معينة معاً)
        // يغطي الحالات القصوى للتقاطعات التسويقية
        // =========================================================================
        if (!empty($cleaningServices)) {
            $exclusiveCoupon = Coupon::firstOrCreate(
                ['code' => 'SPECIALCLEAN'],
                [
                    'type' => 'fixed',
                    'value' => 70.00,
                    'max_discount' => null,
                    'min_booking_amount' => 250.00,
                    'usage_limit' => 20,
                    'user_usage_limit' => 1,
                    'used_count' => 0,
                    'start_date' => Carbon::now(),
                    'end_date' => Carbon::now()->addDays(5),
                    'is_for_all_users' => false,    // 🌟 كستمر محدد
                    'is_for_all_services' => false, // 🌟 خدمة محددة
                    'status' => 'active',
                ]
            );
            
            // قفل التقاطع في الجداول الوسيطة
            $exclusiveCoupon->users()->sync([$customers->first()->id]);
            $exclusiveCoupon->services()->sync([$cleaningServices[0]]);
        }

        $this->command->info('✅ تم ملء قاعدة البيانات بكوبونات متنوعة تغطي كافة الحالات التشغيلية بنجاح!');
    }
}