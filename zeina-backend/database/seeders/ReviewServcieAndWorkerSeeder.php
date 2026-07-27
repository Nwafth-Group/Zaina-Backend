<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewServcieAndWorkerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = Service::all();
        $customer = User::where('type', 'customer')->first() ?? User::first();

        $comments = [
            'خدمة ممتازة جداً وطاقم عمل احترافي وملتزم بالمواعيد.',
            'تجربة رائعة وسرعة في الإنجاز، أنصح بالتعامل معهم بشدة.',
            'نظافة عالية ودقة متناهية في التفاصيل، شكرًا لكم.',
            'خدمة ممتازة وأسعار مناسبة جداً مقارنة بالجودة المقدمة.',
            'انضباط تام وأسلوب راقي من فريق العمل.',
        ];

        foreach ($services as $service) {
            $bookings = Booking::where('service_id', $service->id)->get();

            foreach ($bookings as $idx => $booking) {
                $rating = rand(4, 5);
                Review::firstOrCreate(
                    [
                        'booking_id' => $booking->id,
                        'reviewable_type' => Service::class,
                        'reviewable_id' => $service->id,
                    ],
                    [
                        'customer_id' => $booking->customer_id ?? ($customer ? $customer->id : 1),
                        'rating' => $rating,
                        'comment' => $comments[($service->id + $idx) % count($comments)],
                        'rating_percentage' => ($rating / 5) * 100,
                        'status' => 'approved',
                    ]
                );
            }

            // Sync average rating for the service
            $avg = Review::where('reviewable_type', Service::class)
                ->where('reviewable_id', $service->id)
                ->where('status', 'approved')
                ->avg('rating');

            if ($avg) {
                $service->update(['avg_rating' => round((float) $avg, 2)]);
            }
        }
    }
}
