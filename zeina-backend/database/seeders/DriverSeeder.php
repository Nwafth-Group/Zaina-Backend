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
            ['name' => 'محمود السيد مصطفى', 'mobile' => '01011122334', 'email' => 'mahmoud.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'أحمد عبد الفتاح علي', 'mobile' => '01022233445', 'email' => 'ahmed.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'محمد إبراهيم حسن', 'mobile' => '01133344556', 'email' => 'mohamed.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'شريف كمال التوني', 'mobile' => '01144455667', 'email' => 'sherif.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'خالد عبد المجيد مرسي', 'mobile' => '01255566778', 'email' => 'khaled.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'ياسر فتحي زكي', 'mobile' => '01266677889', 'email' => 'yasser.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'طارق عبد الوهاب رشاد', 'mobile' => '01577788990', 'email' => 'tarek.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'عمرو حسام الدين فؤاد', 'mobile' => '01588899001', 'email' => 'amr.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'هاني سليمان عبد الهادي', 'mobile' => '01099900112', 'email' => 'hani.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'مصطفى كامل عبد الغني', 'mobile' => '01100011223', 'email' => 'mostafa.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'سامح عبد السلام متولي', 'mobile' => '01211122334', 'email' => 'sameh.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'وحيد فاروق حسنين', 'mobile' => '01522233445', 'email' => 'waheed.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'كريم صلاح الدين عثمان', 'mobile' => '01033344556', 'email' => 'kareem.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'إسلام رجب عبد المنعم', 'mobile' => '01144455668', 'email' => 'islam.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
            ['name' => 'حازم نبيل بدوي', 'mobile' => '01255566779', 'email' => 'hazem.driver@zeina.com', 'gender' => 'male', 'status' => 'active'],
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
