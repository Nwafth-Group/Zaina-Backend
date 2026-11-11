<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
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

    public function run(): void
    {
        // 1. تنظيف الجدول
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Announcement::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $announcements = [
            [
                'title' => 'شقتك. لا تنسَ التحقق من قائمة رغباتك',
                'title_ar' => 'شقتك. لا تنسَ التحقق من قائمة رغباتك',
                'title_en' => 'Apartment. Don\'t forget to check your last wishlist',
                'body' => 'عملاءنا الأعزاء، لاحظنا وجود عناصر معلقة في قائمة خدماتك المنزلية المفضلة. احجز الآن للحصول على أفضل أسعار بالساعة مع أمهر المتخصصين لتنظيف وصيانة شقتك!',
                'body_ar' => 'عملاءنا الأعزاء، لاحظنا وجود عناصر معلقة في قائمة خدماتك المنزلية المفضلة. احجز الآن للحصول على أفضل أسعار بالساعة مع أمهر المتخصصين لتنظيف وصيانة شقتك!',
                'body_en' => 'Dear Customers, We notice you have pending items in your home service wishlist. Book now to get the best hourly rates and top-rated professionals for your apartment cleaning and maintenance!',
                'created_at' => now()->subMinutes(4),
                'updated_at' => now()->subMinutes(4),
            ],
            [
                'title' => 'إعلان. عروض جديدة لعام 2025',
                'title_ar' => 'إعلان. عروض جديدة لعام 2025',
                'title_en' => 'Announcement. New offer coming 2025',
                'body' => 'يسعدنا إطلاق باقاتنا الفاخرة الجديدة كلياً للمنازل! مصممة لجعل مهام التنظيف العميق الموسمية أسهل وأسرع وأكثر كفاءة، لتعزيز تجربتكم مع زينة.',
                'body_ar' => 'يسعدنا إطلاق باقاتنا الفاخرة الجديدة كلياً للمنازل! مصممة لجعل مهام التنظيف العميق الموسمية أسهل وأسرع وأكثر كفاءة، لتعزيز تجربتكم مع زينة.',
                'body_en' => 'We are thrilled to introduce the launch of our brand-new home luxury packages! Designed to make your seasonal deep cleaning tasks simpler, faster, and more efficient. This marks a significant step toward enhancing your experience with Ziena.',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],
            [
                'title' => 'إعلان. عروض وتخفيضات خاصة',
                'title_ar' => 'إعلان. عروض وتخفيضات خاصة',
                'title_en' => 'Announcement. New offer coming 2024',
                'body' => 'استعد لأكبر موسم تخفيضات في العام! تطبيق زينة يقدم خصومات تصل إلى 40% على جميع خدمات العناية والتنظيف المنزلي المميزة لفترة محدودة. لا تفوت الفرصة واحجز موعدك اليوم!',
                'body_ar' => 'استعد لأكبر موسم تخفيضات في العام! تطبيق زينة يقدم خصومات تصل إلى 40% على جميع خدمات العناية والتنظيف المنزلي المميزة لفترة محدودة. لا تفوت الفرصة واحجز موعدك اليوم!',
                'body_en' => 'Get ready for the biggest discount event of the year. Ziena Application offers up to 40% off on all premium beauty and home care services for a limited time. Don\'t miss out and schedule your slot today!',
                'created_at' => now()->subYears(1),
                'updated_at' => now()->subYears(1),
            ],
            [
                'title' => 'إعلان. خصومات موسم الشتاء الخاصة',
                'title_ar' => 'إعلان. خصومات موسم الشتاء الخاصة',
                'title_en' => 'Announcement. Winter Season Special Discount',
                'body' => 'استمتع بالدفء والراحة بينما نتولى نحن العمل. باقات خاصة للتجهيز الشتوي للمنزل تشمل التعقيم العميق للمطابخ وغسيل السجاد بأسعار لا تُنافس بالساعة.',
                'body_ar' => 'استمتع بالدفء والراحة بينما نتولى نحن العمل. باقات خاصة للتجهيز الشتوي للمنزل تشمل التعقيم العميق للمطابخ وغسيل السجاد بأسعار لا تُنافس بالساعة.',
                'body_en' => 'Stay cozy while we handle the work. Special packages for winter home preparation, including deep kitchen sanitization and carpet vacuuming are now live at unmatched hourly prices.',
                'created_at' => now()->subYears(1),
                'updated_at' => now()->subYears(1),
            ],
        ];

        // 🌟 2. المسار الثابت والمضمون من مجلد الـ public الأساسي للـ Dummy
        $sourcePath = public_path('dummy/banner.jpg');

        // Deliver every seeded announcement to all customers.
        $customerIds = User::query()->where('type', 'customer')->pluck('id')->all();

        foreach ($announcements as $data) {
            $announcement = Announcement::create($data);
            $announcement->readers()->sync($customerIds);

            if (file_exists($sourcePath)) {
                // 🌟 الحل السحري: بنعمل كاش للمحتوى وبننشئ كائن UploadedFile حركي لكل لفة بـ الـ Loop
                // عشان الـ MediaService ما تحذفش الملف المصدر أثناء الرفع وتخليه متاح للجميع
                $tempPath = sys_get_temp_dir().'/temp_banner_'.uniqid().'.jpg';
                copy($sourcePath, $tempPath);

                $uploadedFile = new UploadedFile(
                    $tempPath,
                    'banner.jpg',
                    'image/jpeg',
                    null,
                    true // Test mode ليتخطى فحص الـ HTTP POST
                );

                // استدعاء الميديا سيرفس لتخزين السجل في جدول الـ media
                $this->mediaService->upload($uploadedFile, $announcement, 'announcement/image', 'public');

                // تنظيف الملف المؤقت بعد الرفع
                if (file_exists($tempPath)) {
                    @unlink($tempPath);
                }
            }
        }
    }
}
