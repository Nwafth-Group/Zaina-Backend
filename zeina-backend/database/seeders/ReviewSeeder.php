<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Service;
use App\Models\Worker;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReviewSeeder extends Seeder
{
    use WithoutModelEvents;

    private array $positiveComments = [
        'خدمة ممتازة وتعامل راقٍ، شكرًا لكم.',
        'كانت في الموعد تمامًا والنتيجة فاقت توقعاتي.',
        'احترافية ونظافة عالية، أنصح بها بشدة.',
        'متعاونة ومتقنة لعملها، تجربة رائعة.',
        'سعدت بالخدمة وسأكرر الحجز بالتأكيد.',
        'أداء مميز واهتمام واضح بالتفاصيل.',
    ];

    private array $neutralComments = [
        'الخدمة جيدة لكنها تأخرت قليلًا عن الموعد.',
        'لا بأس بها، لكن أتمنى مزيدًا من الاهتمام بالتفاصيل.',
        'تجربة مقبولة بشكل عام.',
        'جيدة، مع وجود مجال للتحسين.',
    ];

    private array $negativeComments = [
        'الخدمة دون المستوى المتوقع.',
        'تأخر ملحوظ وعدم التزام بالوقت.',
        'أتمنى تحسين جودة الخدمة مستقبلًا.',
    ];

    public function run(): void
    {
        $completedBookings = Booking::where('status', 'completed')
            ->with(['workers', 'service'])
            ->get();

        if ($completedBookings->isEmpty()) {
            $this->command->warn('لا توجد حجوزات مكتملة لإنشاء تقييمات. شغّل BookingSeeder أولًا.');
            return;
        }

        $reviewedWorkerIds = [];
        $reviewedServiceIds = [];
        $reviewCount = 0;

        DB::beginTransaction();
        try {
            foreach ($completedBookings as $booking) {
                if (rand(1, 100) > 75) {
                    continue;
                }

                // --- Service review (one per booking) ---
                if ($booking->service) {
                    $exists = Review::where('booking_id', $booking->id)
                        ->where('reviewable_type', Service::class)
                        ->where('reviewable_id', $booking->service_id)
                        ->exists();

                    if (! $exists) {
                        $rating = $this->weightedRating();
                        Review::create([
                            'booking_id' => $booking->id,
                            'reviewable_type' => Service::class,
                            'reviewable_id' => $booking->service_id,
                            'customer_id' => $booking->customer_id,
                            'rating' => $rating,
                            'comment' => $this->commentFor($rating),
                            'status' => 'approved',
                        ]);
                        $reviewedServiceIds[$booking->service_id] = true;
                        $reviewCount++;
                    }
                }

                // --- Worker review(s) — one per active assigned worker ---
                foreach ($booking->workers as $worker) {
                    $exists = Review::where('booking_id', $booking->id)
                        ->where('reviewable_type', Worker::class)
                        ->where('reviewable_id', $worker->id)
                        ->exists();

                    if (! $exists) {
                        $rating = $this->weightedRating();
                        Review::create([
                            'booking_id' => $booking->id,
                            'reviewable_type' => Worker::class,
                            'reviewable_id' => $worker->id,
                            'customer_id' => $booking->customer_id,
                            'rating' => $rating,
                            'comment' => $this->commentFor($rating),
                            'status' => 'approved',
                        ]);
                        $reviewedWorkerIds[$worker->id] = true;
                        $reviewCount++;
                    }
                }
            }

            // Ensure EVERY service in the database has approved reviews
            $allServices = Service::all();
            foreach ($allServices as $svc) {
                $hasApprovedReview = Review::where('reviewable_type', Service::class)
                    ->where('reviewable_id', $svc->id)
                    ->where('status', 'approved')
                    ->exists();

                if (! $hasApprovedReview) {
                    $testBooking = Booking::where('service_id', $svc->id)->first();
                    $customerId = $testBooking ? $testBooking->customer_id : (Booking::first()->customer_id ?? 1);

                    $rating = rand(4, 5);
                    Review::create([
                        'booking_id' => $testBooking ? $testBooking->id : null,
                        'reviewable_type' => Service::class,
                        'reviewable_id' => $svc->id,
                        'customer_id' => $customerId,
                        'rating' => $rating,
                        'comment' => $this->positiveComments[$svc->id % count($this->positiveComments)],
                        'status' => 'approved',
                    ]);
                    $reviewedServiceIds[$svc->id] = true;
                    $reviewCount++;
                }
            }

            $this->syncAverages(Worker::class, array_keys($reviewedWorkerIds));
            $this->syncAverages(Service::class, array_keys($reviewedServiceIds));

            DB::commit();

            $this->command->info(sprintf(
                'تم إنشاء %d تقييمًا (%d عاملًا و%d خدمة) وتحديث متوسطات التقييم.',
                $reviewCount,
                count($reviewedWorkerIds),
                count($reviewedServiceIds),
            ));
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error('حدث خطأ في ReviewSeeder: '.$e->getMessage());
        }
    }

    private function weightedRating(): int
    {
        $roll = rand(1, 100);

        return match (true) {
            $roll <= 60 => 5,
            $roll <= 85 => 4,
            $roll <= 93 => 3,
            $roll <= 97 => 2,
            default => 1,
        };
    }

    private function commentFor(int $rating): string
    {
        $pool = match (true) {
            $rating >= 4 => $this->positiveComments,
            $rating === 3 => $this->neutralComments,
            default => $this->negativeComments,
        };

        return $pool[array_rand($pool)];
    }

    private function syncAverages(string $modelClass, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        foreach ($ids as $id) {
            $avg = Review::where('reviewable_type', $modelClass)
                ->where('reviewable_id', $id)
                ->where('status', 'approved')
                ->avg('rating');

            if ($avg !== null) {
                $modelClass::where('id', $id)->update([
                    'avg_rating' => round((float) $avg, 2),
                ]);
            }
        }
    }
}
