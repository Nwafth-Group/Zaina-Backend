<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ReviewServcieAndWorkerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // يمكنك هنا إنشاء مراجعات وهمية للخدمات والعاملين باستخدام Factories أو بشكل يدوي
        // مثال:
        // Review::factory()->count(50)->create(); // إذا كان لديك Factory معد للمراجعات

        Review::create([
            'booking_id' => 1, // تأكد من وجود حجز بهذا المعرف
            'customer_id' => 1, // تأكد من وجود مستخدم بهذا المعرف
            'reviewable_type' => Service::class, // أو Worker::class حسب ما تريد مراجعته
            'reviewable_id' => 1, // معرف الخدمة أو العامل الذي تريد مراجعته
            'rating' => 5,
            'comment' => 'خدمة ممتازة!',
        ]);

        Review::create([
            'booking_id' => 2, // تأكد من وجود حجز بهذا المعرف
            'customer_id' => 1, // تأكد من وجود مستخدم بهذا المعرف
            'reviewable_type' => Service::class, // أو Worker::class حسب ما تريد مراجعته
            'reviewable_id' => 2, // معرف الخدمة أو العامل الذي تريد مراجعته
            'rating' => 4,
            'comment' => 'خدمة جيدة ولكن يمكن تحسينها.',
        ]);

        Review::create([
            'booking_id' => 3, // تأكد من وجود حجز بهذا المعرف
            'customer_id' => 2, // تأكد من وجود مستخدم بهذا المعرف
            'reviewable_type' => Service::class, // أو Worker::class حسب ما تريد مراجعته
            'reviewable_id' => 1, // معرف الخدمة أو العامل الذي تريد مراجعته
            'rating' => 3,
            'comment' => 'الخدمة كانت متوسطة.',
        ]);

        Review::create([
            'booking_id' => 4, // تأكد من وجود حجز بهذا المعرف
            'customer_id' => 2, // تأكد من وجود مستخدم بهذا المعرف
            'reviewable_type' => Service::class, // أو Worker::class حسب ما تريد مراجعته
            'reviewable_id' => 2, // معرف الخدمة أو العامل الذي تريد مراجعته
            'rating' => 2,
            'comment' => 'الخدمة لم تكن كما توقعت.',
        ]);
    }
}
