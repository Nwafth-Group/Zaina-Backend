<?php

namespace Database\Seeders;

use App\Models\Announcement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AnnouncementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function up(): void
    {
        // لتنظيف الجدول قبل الضخ عشان ما تتكرر البيانات كل ما تشغل الـ Seeder
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Announcement::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 🌟 اسم الصورة الفعلي المخزن بداخل storage/app/public/announcements
        // بما أنك رفعت صورة بالتست، تأكد من اسمها وحطه هون، مثلاً: 'announcements/default.png'
        // وإذا ما في اسم محدد، هاد الاسم القياسي اللي اعتمدناه بالـ Controller:
        $imagePath = 'announcement/image/1dJozWoEMxx5Yvbyk4myXq7eLHeCzLqHDQ5uLdpA.jpg';

        $announcements = [
            [
                'title' => 'Apartment. Don\'t forget to check your last wishlist',
                'body' => 'Dear Customers, We notice you have pending items in your home service wishlist. Book now to get the best hourly rates and top-rated professionals for your apartment cleaning and maintenance!',
                'image' => $imagePath,
                'created_at' => now()->subMinutes(4), // بيظهر بالـ UI: "4m"
                'updated_at' => now()->subMinutes(4),
            ],
            [
                'title' => 'Announcement. New offer coming 2025',
                'body' => 'We are thrilled to introduce the launch of our brand-new home luxury packages! Designed to make your seasonal deep cleaning tasks simpler, faster, and more efficient. This marks a significant step toward enhancing your experience with Zeinah.',
                'image' => $imagePath,
                'created_at' => now()->subDays(1), // بيظهر بالـ UI: "1d"
                'updated_at' => now()->subDays(1),
            ],
            [
                'title' => 'Announcement. New offer coming 2024',
                'body' => 'Get ready for the biggest discount event of the year. Zeinah Application offers up to 40% off on all premium beauty and home care services for a limited time. Don\'t miss out and schedule your slot today!',
                'image' => $imagePath,
                'created_at' => now()->subYears(1), // بيظهر بالـ UI: "1y"
                'updated_at' => now()->subYears(1),
            ],
            [
                'title' => 'Announcement. Winter Season Special Discount',
                'body' => 'Stay cozy while we handle the work. Special packages for winter home preparation, including deep kitchen sanitization and carpet vacuuming are now live at unmatched hourly prices.',
                'image' => $imagePath,
                'created_at' => now()->subYears(1),
                'updated_at' => now()->subYears(1),
            ]
        ];

        foreach ($announcements as $data) {
            Announcement::create($data);
        }
    }
}