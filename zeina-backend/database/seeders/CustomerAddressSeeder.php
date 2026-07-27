<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ServiceZone;
use App\Models\CustomerAddress;
use Faker\Factory as Faker;

class CustomerAddressSeeder extends Seeder
{
    public function run(): void
    {
        // استخدام Faker باللغة العربية
        $faker = Faker::create('ar_SA'); 

        // جلب العملاء مع المستخدمة سارة التي أضفناها للتجارب
        $users = User::where('type', 'customer')->get(); 

        if ($users->isEmpty()) {
            $users = User::take(5)->get(); 
        }

        $serviceZones = ServiceZone::all();

        if ($users->isEmpty()) {
            $this->command->info('يرجى التأكد من تشغيل UserSeeder أولاً لتواجد Users.');
            return;
        }

        foreach ($users as $user) {
            $zoneId = $serviceZones->isNotEmpty() ? $serviceZones->random()->id : null;
            
            // 1. إنشاء عنوان افتراضي (المنزل)
            CustomerAddress::create([
                'user_id'            => $user->id,
                'service_zone_id'    => $zoneId,
                'latitude'           => $faker->latitude($min = 29.9, $max = 30.1), // إحداثيات قريبة من القاهرة
                'longitude'          => $faker->longitude($min = 31.1, $max = 31.4),
                'label'              => 'Home',
                'street'             => 'شارع ' . $faker->firstName,
                'building_name'      => 'عمارة ' . $faker->lastName,
                'building_no'        => (string) $faker->numberBetween(1, 100),
                'floor'              => (string) $faker->numberBetween(1, 15),
                'apartment_no'       => (string) $faker->numberBetween(1, 50),
                'contact_name'       => $user->first_name . ' ' . $user->last_name, // اسم العميل نفسه
                'phone_number'       => $user->phone_number ?? '010' . $faker->randomNumber(8, true),
                'secondary_phone'    => null, // اختياري حسب الفيجما
                'serviceable_status' => 'serviceable',
                'notes'              => 'الرجاء الاتصال قبل الوصول بـ 15 دقيقة',
                'is_default'         => true,
                'is_active'          => true,
            ]);

            // 2. إنشاء عنوان إضافي (العمل)
            CustomerAddress::create([
                'user_id'            => $user->id,
                'service_zone_id'    => $zoneId,
                'latitude'           => $faker->latitude($min = 29.9, $max = 30.1),
                'longitude'          => $faker->longitude($min = 31.1, $max = 31.4),
                'label'              => 'Work',
                'street'             => 'شارع ' . $faker->streetName,
                'building_name'      => 'برج ' . $faker->company,
                'building_no'        => (string) $faker->numberBetween(1, 50),
                'floor'              => (string) $faker->numberBetween(1, 20),
                'apartment_no'       => 'مكتب ' . $faker->numberBetween(100, 500),
                'contact_name'       => 'مكتب ' . $user->first_name,
                'phone_number'       => '011' . $faker->randomNumber(8, true),
                'secondary_phone'    => '012' . $faker->randomNumber(8, true),
                'serviceable_status' => 'serviceable',
                'notes'              => 'يرجى ترك الطلب عند موظف الاستقبال في الطابق الأرضي',
                'is_default'         => false,
                'is_active'          => true,
            ]);
        }
    }
}