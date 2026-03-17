<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Provider;
use App\Models\Service;
use App\Models\City;
use App\Models\Area;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        // 1. جلب بيانات موجودة مسبقاً لربطها (مدير، خدمة، مدينة، منطقة)
        $admin = User::first(); // لغايات الـ created_by
        $service = Service::first(); // نفترض وجود خدمة واحدة على الأقل
        $city = City::first(); // نفترض وجود مدينة
        $area = Area::where('city_id', $city->id ?? 0)->first(); // منطقة تابعة للمدينة

        // 2. إنشاء مزود خدمة "فرد" (Individual)
        $individualProvider = Provider::firstOrCreate(
            ['phone_number' => '500000001'], // التأكد من عدم التكرار
            [
                'type' => 'individual',
                'fullname' => 'Ahmed Ali',
                'gender' => 'Male',
                'phone_code' => '+20',
                'code' => 'PRV-1001',
                'status' => 'active',
                'internal_notes' => 'مزود خدمة ممتاز، متخصص في التنظيف.',
                'rating_average' => 4.80,
                'created_by' => $admin->id ?? null,
            ]
        );

        // 3. إنشاء مزود خدمة "شركة" (Company)
        $companyProvider = Provider::firstOrCreate(
            ['phone_number' => '500000002'],
            [
                'type' => 'company',
                'fullname' => 'Cleaners Co.',
                'gender' => 'Male', // أو Female حسب اختيارك للشركات
                'phone_code' => '+20',
                'code' => 'PRV-1002',
                'status' => 'active',
                'internal_notes' => 'شركة تنظيف توفر عمالة متعددة.',
                'rating_average' => 4.50,
                'created_by' => $admin->id ?? null,
            ]
        );

        // 4. ربط المزودين بالخدمات والتغطية (إذا كانت متوفرة في الداتا بيس)
        if ($service && $city) {
            
            // --- ربط الفرد ---
            // ربط بالخدمة
            DB::table('provider_services')->updateOrInsert(
                ['provider_id' => $individualProvider->id, 'service_id' => $service->id],
                ['is_enabled' => true, 'created_by' => $admin->id ?? null, 'created_at' => now(), 'updated_at' => now()]
            );
            
            // ربط بالمنطقة/المدينة
            DB::table('area_providers')->updateOrInsert(
                ['provider_id' => $individualProvider->id, 'city_id' => $city->id, 'area_id' => $area->id ?? null],
                ['is_enabled' => true, 'created_by' => $admin->id ?? null, 'created_at' => now(), 'updated_at' => now()]
            );


            // --- ربط الشركة ---
            // ربط بالخدمة
            DB::table('provider_services')->updateOrInsert(
                ['provider_id' => $companyProvider->id, 'service_id' => $service->id],
                ['is_enabled' => true, 'created_by' => $admin->id ?? null, 'created_at' => now(), 'updated_at' => now()]
            );

            // ربط بالمدينة بالكامل (area_id = null)
            DB::table('area_providers')->updateOrInsert(
                ['provider_id' => $companyProvider->id, 'city_id' => $city->id, 'area_id' => null],
                ['is_enabled' => true, 'created_by' => $admin->id ?? null, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}