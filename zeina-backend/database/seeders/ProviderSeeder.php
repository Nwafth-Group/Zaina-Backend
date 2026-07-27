<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        // 1. جلب بيانات موجودة مسبقاً لربطها
        $admin = User::first();
        $service = Service::first();

        // 2. إنشاء مزود خدمة "فرد" (Individual)
        $individualProvider = Provider::firstOrCreate(
            ['phone_number' => '500000001'],
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
                'gender' => 'Male',
                'phone_code' => '+20',
                'code' => 'PRV-1002',
                'status' => 'active',
                'internal_notes' => 'شركة تنظيف توفر عمالة متعددة.',
                'rating_average' => 4.50,
                'created_by' => $admin->id ?? null,
            ]
        );

        // 4. ربط المزودين بالخدمات
        if ($service) {
            DB::table('provider_services')->updateOrInsert(
                ['provider_id' => $individualProvider->id, 'service_id' => $service->id],
                ['is_enabled' => true, 'created_by' => $admin->id ?? null, 'created_at' => now(), 'updated_at' => now()]
            );

            DB::table('provider_services')->updateOrInsert(
                ['provider_id' => $companyProvider->id, 'service_id' => $service->id],
                ['is_enabled' => true, 'created_by' => $admin->id ?? null, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}