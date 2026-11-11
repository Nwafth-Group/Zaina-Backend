<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->string('title_en')->nullable()->after('title_ar');
            $table->text('body_ar')->nullable()->after('body');
            $table->text('body_en')->nullable()->after('body_ar');
        });

        // Seed / migrate existing data with Arabic & English
        $translations = [
            1 => [
                'title_ar' => 'شقتك. لا تنسَ التحقق من قائمة رغباتك',
                'title_en' => 'Apartment. Don\'t forget to check your last wishlist',
                'body_ar' => 'عملاءنا الأعزاء، لاحظنا وجود عناصر معلقة في قائمة خدماتك المنزلية المفضلة. احجز الآن للحصول على أفضل أسعار بالساعة مع أمهر المتخصصين لتنظيف وصيانة شقتك!',
                'body_en' => 'Dear Customers, We notice you have pending items in your home service wishlist. Book now to get the best hourly rates and top-rated professionals for your apartment cleaning and maintenance!',
            ],
            2 => [
                'title_ar' => 'إعلان. عروض جديدة لعام 2025',
                'title_en' => 'Announcement. New offer coming 2025',
                'body_ar' => 'يسعدنا إطلاق باقاتنا الفاخرة الجديدة كلياً للمنازل! مصممة لجعل مهام التنظيف العميق الموسمية أسهل وأسرع وأكثر كفاءة، لتعزيز تجربتكم مع زينة.',
                'body_en' => 'We are thrilled to introduce the launch of our brand-new home luxury packages! Designed to make your seasonal deep cleaning tasks simpler, faster, and more efficient. This marks a significant step toward enhancing your experience with Ziena.',
            ],
            3 => [
                'title_ar' => 'إعلان. عروض وتخفيضات خاصة',
                'title_en' => 'Announcement. New offer coming 2024',
                'body_ar' => 'استعد لأكبر موسم تخفيضات في العام! تطبيق زينة يقدم خصومات تصل إلى 40% على جميع خدمات العناية والتنظيف المنزلي المميزة لفترة محدودة. لا تفوت الفرصة واحجز موعدك اليوم!',
                'body_en' => 'Get ready for the biggest discount event of the year. Ziena Application offers up to 40% off on all premium beauty and home care services for a limited time. Don\'t miss out and schedule your slot today!',
            ],
            4 => [
                'title_ar' => 'إعلان. خصومات موسم الشتاء الخاصة',
                'title_en' => 'Announcement. Winter Season Special Discount',
                'body_ar' => 'استمتع بالدفء والراحة بينما نتولى نحن العمل. باقات خاصة للتجهيز الشتوي للمنزل تشمل التعقيم العميق للمطابخ وغسيل السجاد بأسعار لا تُنافس بالساعة.',
                'body_en' => 'Stay cozy while we handle the work. Special packages for winter home preparation, including deep kitchen sanitization and carpet vacuuming are now live at unmatched hourly prices.',
            ],
        ];

        foreach ($translations as $id => $data) {
            DB::table('announcements')
                ->where('id', $id)
                ->update($data);
        }

        // For any other existing records that didn't match the specific IDs, copy title and body
        DB::table('announcements')
            ->whereNull('title_en')
            ->update([
                'title_en' => DB::raw('title'),
                'title_ar' => DB::raw('title'),
                'body_en' => DB::raw('body'),
                'body_ar' => DB::raw('body'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'title_en', 'body_ar', 'body_en']);
        });
    }
};

