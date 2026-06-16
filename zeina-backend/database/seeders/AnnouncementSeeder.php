<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Service\MediaService; 
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class AnnouncementSeeder extends Seeder
{
    protected $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. تنظيف الجدول
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Announcement::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $announcements = [
            [
                'title' => 'Apartment. Don\'t forget to check your last wishlist',
                'body' => 'Dear Customers, We notice you have pending items in your home service wishlist. Book now to get the best hourly rates and top-rated professionals for your apartment cleaning and maintenance!',
                'created_at' => now()->subMinutes(4),
                'updated_at' => now()->subMinutes(4),
            ],
            [
                'title' => 'Announcement. New offer coming 2025',
                'body' => 'We are thrilled to introduce the launch of our brand-new home luxury packages! Designed to make your seasonal deep cleaning tasks simpler, faster, and more efficient. This marks a significant step toward enhancing your experience with Zeinah.',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],
            [
                'title' => 'Announcement. New offer coming 2024',
                'body' => 'Get ready for the biggest discount event of the year. Zeinah Application offers up to 40% off on all premium beauty and home care services for a limited time. Don\'t miss out and schedule your slot today!',
                'created_at' => now()->subYears(1),
                'updated_at' => now()->subYears(1),
            ],
            [
                'title' => 'Announcement. Winter Season Special Discount',
                'body' => 'Stay cozy while we handle the work. Special packages for winter home preparation, including deep kitchen sanitization and carpet vacuuming are now live at unmatched hourly prices.',
                'created_at' => now()->subYears(1),
                'updated_at' => now()->subYears(1),
            ]
        ];

        // 🌟 2. تحديث المسار بناءً على هيكل مشروعك الفعلي بالصورة:
        // انسخ اسم الصورة الطويل كامل من الـ VS Code وضعه مكان النقاط (...)
        $fileName = '1dJozWoEMxx5Yvbyk4myXq7eLHeCzLqHDQ5uLdpA.jpg'; // 👈 تأكد من الأحرف كاملة هنا
        $sourcePath = storage_path('app/public/announcement/image/' . $fileName);

        foreach ($announcements as $data) {
            $announcement = Announcement::create($data);

            // 3. تحويل الملف وتمريره للـ MediaService
            if (file_exists($sourcePath)) {
                $uploadedFile = new UploadedFile(
                    $sourcePath,
                    $fileName,
                    'image/jpeg',
                    null,
                    true // وضع التست للسماح بالنسخ المباشر بالخلفية
                );

                // استدعاء الميديا سيرفس لتربط السجلات بجدول الـ media الفعلي بالسيستم
                $this->mediaService->upload($uploadedFile, $announcement, 'announcement/image', 'public');
            }
        }
    }
}