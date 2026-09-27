<?php

namespace Database\Seeders;

use App\Models\Driver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        $driversData = [
            [
                'name' => 'محمود السيد مصطفى',
                'mobile' => '01011122334',
                'email' => 'mahmoud.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Toyota HiAce (فان)',
                'vehicle_plate' => 'أ ب ج 1234',
                'vehicle_capacity' => 6,
                'status' => 'active'
            ],
            [
                'name' => 'أحمد عبد الفتاح علي',
                'mobile' => '01022233445',
                'email' => 'ahmed.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Hyundai H-1 (ميني فان)',
                'vehicle_plate' => 'س ص ع 5678',
                'vehicle_capacity' => 6,
                'status' => 'active'
            ],
            [
                'name' => 'محمد إبراهيم حسن',
                'mobile' => '01133344556',
                'email' => 'mohamed.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Nissan Urvan (فان)',
                'vehicle_plate' => 'د ذ ر 9101',
                'vehicle_capacity' => 5,
                'status' => 'active'
            ],
            [
                'name' => 'شريف كمال التوني',
                'mobile' => '01144455667',
                'email' => 'sherif.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Ford Transit Custom',
                'vehicle_plate' => 'ز س ش 1121',
                'vehicle_capacity' => 7,
                'status' => 'active'
            ],
            [
                'name' => 'خالد عبد المجيد مرسي',
                'mobile' => '01255566778',
                'email' => 'khaled.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Toyota Innova (عائلية)',
                'vehicle_plate' => 'ص ض ط 3141',
                'vehicle_capacity' => 5,
                'status' => 'active'
            ],
            [
                'name' => 'ياسر فتحي زكي',
                'mobile' => '01266677889',
                'email' => 'yasser.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Kia Carnival',
                'vehicle_plate' => 'ع غ ف 5161',
                'vehicle_capacity' => 5,
                'status' => 'active'
            ],
            [
                'name' => 'طارق عبد الوهاب رشاد',
                'mobile' => '01577788990',
                'email' => 'tarek.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Toyota HiAce (فان)',
                'vehicle_plate' => 'ق ك ل 7181',
                'vehicle_capacity' => 6,
                'status' => 'active'
            ],
            [
                'name' => 'عمرو حسام الدين فؤاد',
                'mobile' => '01588899001',
                'email' => 'amr.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Hyundai H-1 (ميني فان)',
                'vehicle_plate' => 'م ن هـ 9202',
                'vehicle_capacity' => 6,
                'status' => 'active'
            ],
            [
                'name' => 'هاني سليمان عبد الهادي',
                'mobile' => '01099900112',
                'email' => 'hani.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Ford Transit Custom',
                'vehicle_plate' => 'و ي أ 1222',
                'vehicle_capacity' => 7,
                'status' => 'active'
            ],
            [
                'name' => 'مصطفى كامل عبد الغني',
                'mobile' => '01100011223',
                'email' => 'mostafa.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Nissan Urvan (فان)',
                'vehicle_plate' => 'ب ت ث 3242',
                'vehicle_capacity' => 5,
                'status' => 'active'
            ],
            [
                'name' => 'سامح عبد السلام متولي',
                'mobile' => '01211122334',
                'email' => 'sameh.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Toyota HiAce (فان)',
                'vehicle_plate' => 'ج ح خ 5262',
                'vehicle_capacity' => 6,
                'status' => 'active'
            ],
            [
                'name' => 'وحيد فاروق حسنين',
                'mobile' => '01522233445',
                'email' => 'waheed.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Toyota Innova (عائلية)',
                'vehicle_plate' => 'د ذ ر 7282',
                'vehicle_capacity' => 5,
                'status' => 'active'
            ],
            [
                'name' => 'كريم صلاح الدين عثمان',
                'mobile' => '01033344556',
                'email' => 'kareem.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Hyundai Staria (فان حديث)',
                'vehicle_plate' => 'ز س ش 9303',
                'vehicle_capacity' => 6,
                'status' => 'active'
            ],
            [
                'name' => 'إسلام رجب عبد المنعم',
                'mobile' => '01144455668',
                'email' => 'islam.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Ford Transit Custom',
                'vehicle_plate' => 'ص ض ط 1323',
                'vehicle_capacity' => 7,
                'status' => 'active'
            ],
            [
                'name' => 'حازم نبيل بدوي',
                'mobile' => '01255566779',
                'email' => 'hazem.driver@zeina.com',
                'gender' => 'male',
                'vehicle_type' => 'Toyota HiAce (فان)',
                'vehicle_plate' => 'ظ ع غ 3343',
                'vehicle_capacity' => 6,
                'status' => 'active'
            ],
        ];

        $image1 = public_path('assist/workers/1.webp');
        $image2 = public_path('assist/workers/2.webp');

        foreach ($driversData as $index => $data) {
            $driver = Driver::updateOrCreate(
                ['mobile' => $data['mobile']],
                $data
            );

            // Attach avatar image using media relation
            $sourceImage = ($index % 2 === 0) ? $image1 : $image2;
            $originalName = ($index % 2 === 0) ? '1.webp' : '2.webp';

            if (file_exists($sourceImage) && $driver->media()->count() === 0) {
                $targetDir = 'driver/avatar';
                $fileName = 'driver_' . $driver->id . '_' . time() . '.webp';
                $targetPath = $targetDir . '/' . $fileName;

                Storage::disk('public')->put($targetPath, file_get_contents($sourceImage));

                $driver->media()->create([
                    'collection' => 'driver/avatar',
                    'file_name' => $fileName,
                    'mime_type' => 'image/webp',
                    'size' => filesize($sourceImage),
                    'disk' => 'public',
                    'path' => $targetPath,
                    'title' => $originalName,
                    'date' => now()->toDateString(),
                ]);
            }
        }
    }
}
