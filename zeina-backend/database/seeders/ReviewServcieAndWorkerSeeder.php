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

        Review::firstOrCreate([
            'booking_id' => 1,
            'reviewable_type' => Service::class,
            'reviewable_id' => 1,
        ], [
            'customer_id' => 1,
            'rating' => 5,
            'comment' => 'خدمة ممتازة!',
        ]);

        Review::firstOrCreate([
            'booking_id' => 2,
            'reviewable_type' => Service::class,
            'reviewable_id' => 2,
        ], [
            'customer_id' => 1,
            'rating' => 4,
            'comment' => 'خدمة جيدة ولكن يمكن تحسينها.',
        ]);

        Review::firstOrCreate([
            'booking_id' => 3,
            'reviewable_type' => Service::class,
            'reviewable_id' => 1,
        ], [
            'customer_id' => 2,
            'rating' => 3,
            'comment' => 'الخدمة كانت متوسطة.',
        ]);

        Review::firstOrCreate([
            'booking_id' => 4,
            'reviewable_type' => Service::class,
            'reviewable_id' => 2,
        ], [
            'customer_id' => 2,
            'rating' => 2,
            'comment' => 'الخدمة لم تكن كما توقعت.',
        ]);
    }
}
