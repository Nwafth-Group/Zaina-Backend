<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Area;
use App\Models\CustomerAddress;
use Faker\Factory as Faker;

class CustomerAddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // استخدام Faker باللغة العربية عشان البيانات تطلع واقعية
        $faker = Faker::create('ar_SA'); 

        // جلب كل المستخدمين اللي نوعهم عميل (أو جلب أول 10 مستخدمين للتجربة)
        // افترضت إن عندك حقل type بيميز العميل، لو ما عندك احذف شرط الـ where
        $users = User::where('type', 'customer')->get(); 

        if ($users->isEmpty()) {
            $users = User::take(5)->get(); // خطة بديلة لو مافي حقل type
        }

        // جلب جميع المناطق اللي تم إنشاؤها من LocationSeeder
        $areas = Area::all();

        if ($areas->isEmpty() || $users->isEmpty()) {
            $this->command->info('يرجى التأكد من تشغيل LocationSeeder و UserSeeder أولاً.');
            return;
        }

        // إنشاء عناوين لكل مستخدم
        foreach ($users as $user) {
            
            // 1. إنشاء عنوان افتراضي (المنزل)
            CustomerAddress::create([
                'user_id' => $user->id,
                'area_id' => $areas->random()->id, // اختيار منطقة عشوائية من اللي عندنا (المعادي، مدينة نصر، إلخ)
                'label' => 'Home',
                'street' => 'شارع ' . $faker->firstName,
                'building' => (string) $faker->numberBetween(1, 100),
                'floor' => (string) $faker->numberBetween(1, 10),
                'latitude' => $faker->latitude($min = 24, $max = 30), // إحداثيات تقريبية بين مصر والسعودية
                'longitude' => $faker->longitude($min = 31, $max = 46),
                'serviceable_status' => 'serviceable',
                'unit' => 'شقة ' . $faker->numberBetween(1, 20),
                'notes' => 'الرجاء الاتصال عند الوصول',
                'is_default' => true,
                'is_active' => true,
            ]);

            // 2. إنشاء عنوان إضافي (العمل) عشان الموبايل ديفلوبر يجرب التبديل بينهم
            CustomerAddress::create([
                'user_id' => $user->id,
                'area_id' => $areas->random()->id,
                'label' => 'Work',
                'street' => 'شارع ' . $faker->lastName,
                'building' => (string) $faker->numberBetween(1, 50),
                'floor' => (string) $faker->numberBetween(1, 15),
                'latitude' => $faker->latitude($min = 24, $max = 30),
                'longitude' => $faker->longitude($min = 31, $max = 46),
                'serviceable_status' => 'serviceable',
                'unit' => 'مكتب ' . $faker->numberBetween(100, 500),
                'notes' => 'اترك الطلب في الاستقبال',
                'is_default' => false,
                'is_active' => true,
            ]);
        }
    }
}