<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Service\MediaService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class AdSeeder extends Seeder
{
    protected $mediaService;

    // حقن الميديا سيرفس بداخل السيدر
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
        Ad::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 2. داتا الـ Ads التجريبية (روابط متنوعة واختيارية)
        $ads = [
            [
                'title_ar' => 'عرض الصيف الرائع',
                'title_en' => 'Awesome Summer Offer',
                'type' => 'general',
                'text_ar' => 'احصل على أفضل الخدمات المنزلية بأسعار منافسة جداً',
                'text_en' => 'Get the best home services at highly competitive prices',
                'link_type' => 'external',
                'link' => 'https://zeina.app/summer-offer',
                'status' => 'active',
            ],
            [
                'title_ar' => 'كوبون خصم زينة المميز',
                'title_en' => 'Special Zeina Coupon',
                'type' => 'coupon',
                'text_ar' => 'استخدم الكوبون ZEINA20 للحصول على خصم فوري',
                'text_en' => 'Use coupon ZEINA20 to get an instant discount',
                'link_type' => 'internal',
                'link' => 'coupons',
                'status' => 'active',
            ],
            [
                'title_ar' => 'خدمات تنظيف منازل احترافية',
                'title_en' => 'Professional Home Cleaning Services',
                'type' => 'general',
                'text_ar' => 'نقدم خدمات تنظيف متكاملة ومضمونة الجودة بأيدي خبيرات مدربات',
                'text_en' => 'We offer full-service cleaning with guaranteed quality by trained experts',
                'link_type' => 'none',
                'link' => null,
                'status' => 'active',
            ],
        ];

        // 3. المسار الثابت والمضمون بداخل مجلد الـ public
        $index = 1;
        foreach ($ads as $data) {
            $ad = Ad::create($data);

            $sourcePath = public_path("assist/banners/{$index}.webp");
            if (file_exists($sourcePath)) {
                // 🌟 الحل السحري المأمن: عمل نسخة كاش مؤقتة لكل إعلان عشان الداتا تطلع 100% شغالة وما تطلع null
                $tempPath = sys_get_temp_dir().'/temp_ad_'.uniqid().'.webp';
                copy($sourcePath, $tempPath);

                $uploadedFile = new UploadedFile(
                    $tempPath,
                    "{$index}.webp",
                    'image/webp',
                    null,
                    true // وضع التست للنسخ المباشر
                );

                // رفع الميديا وربطها بـ الـ Polymorphic Table
                $this->mediaService->upload($uploadedFile, $ad, 'ads/image', 'public');

                // حذف الملف المؤقت فوراً بعد النجاح
                if (file_exists($tempPath)) {
                    @unlink($tempPath);
                }
            }
            $index++;
        }
    }
}
