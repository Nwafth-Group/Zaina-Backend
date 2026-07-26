<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Service;
use App\Models\Worker;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds ratings & reviews for workers and services off the back of completed
 * bookings, then recomputes the `avg_rating` column on each reviewed worker and
 * service so the admin tables / details modal show realistic numbers.
 *
 * Reviews are polymorphic (reviewable = Worker|Service) and unique per
 * (booking, reviewable) — a completed booking yields one service review and one
 * review per assigned worker.
 */
class ReviewSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<int, string> */
    private array $positiveComments = [
        'خدمة ممتازة وتعامل راقٍ، شكرًا لكم.',
        'كانت في الموعد تمامًا والنتيجة فاقت توقعاتي.',
        'احترافية ونظافة عالية، أنصح بها بشدة.',
        'متعاونة ومتقنة لعملها، تجربة رائعة.',
        'سعدت بالخدمة وسأكرر الحجز بالتأكيد.',
        'أداء مميز واهتمام واضح بالتفاصيل.',
    ];

    /** @var array<int, string> */
    private array $neutralComments = [
        'الخدمة جيدة لكنها تأخرت قليلًا عن الموعد.',
        'لا بأس بها، لكن أتمنى مزيدًا من الاهتمام بالتفاصيل.',
        'تجربة مقبولة بشكل عام.',
        'جيدة، مع وجود مجال للتحسين.',
    ];

    /** @var array<int, string> */
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
                // Not every completed booking gets reviewed (~75% do).
                if (rand(1, 100) > 75) {
                    continue;
                }

                // --- Service review (one per booking) ---
                if ($booking->service) {
                    $rating = $this->weightedRating();
                    Review::firstOrCreate(
                        [
                            'booking_id' => $booking->id,
                            'reviewable_type' => Service::class,
                            'reviewable_id' => $booking->service_id,
                        ],
                        [
                            'customer_id' => $booking->customer_id,
                            'rating' => $rating,
                            'comment' => $this->commentFor($rating),
                            'status' => 'approved',
                        ]
                    );
                    $reviewedServiceIds[$booking->service_id] = true;
                    $reviewCount++;
                }

                // --- Worker review(s) — one per active assigned worker ---
                foreach ($booking->workers as $worker) {
                    $rating = $this->weightedRating();
                    Review::firstOrCreate(
                        [
                            'booking_id' => $booking->id,
                            'reviewable_type' => Worker::class,
                            'reviewable_id' => $worker->id,
                        ],
                        [
                            'customer_id' => $booking->customer_id,
                            'rating' => $rating,
                            'comment' => $this->commentFor($rating),
                            'status' => 'approved',
                        ]
                    );
                    $reviewedWorkerIds[$worker->id] = true;
                    $reviewCount++;
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

                    for ($r = 1; $r <= 3; $r++) {
                        $rating = rand(4, 5);
                        Review::create([
                            'booking_id' => $testBooking ? $testBooking->id : null,
                            'reviewable_type' => Service::class,
                            'reviewable_id' => $svc->id,
                            'customer_id' => $customerId,
                            'rating' => $rating,
                            'comment' => $this->positiveComments[($svc->id + $r) % count($this->positiveComments)],
                            'status' => 'approved',
                        ]);
                    }
                    $reviewedServiceIds[$svc->id] = true;
                    $reviewCount += 3;
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

    /**
     * Recompute and persist `avg_rating` for the given reviewable model ids.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $reviewableType
     * @param  array<int, int>  $ids
     */
    private function syncAverages(string $reviewableType, array $ids): void
    {
        foreach ($ids as $id) {
            $average = Review::where('reviewable_type', $reviewableType)
                ->where('reviewable_id', $id)
                ->where('status', 'approved')
                ->avg('rating');

            $reviewableType::where('id', $id)->update([
                'avg_rating' => round((float) $average, 2),
            ]);
        }
    }

    /**
     * A rating skewed towards happy customers (5s common, 1s rare).
     */
    private function weightedRating(): int
    {
        $pool = [5, 5, 5, 5, 4, 4, 4, 3, 3, 2, 1];

        return $pool[array_rand($pool)];
    }

    /**
     * A comment matching the rating bucket; ~15% leave a rating with no comment.
     */
    private function commentFor(int $rating): ?string
    {
        if (rand(1, 100) <= 15) {
            return null;
        }

        $bucket = match (true) {
            $rating >= 4 => $this->positiveComments,
            $rating === 3 => $this->neutralComments,
            default => $this->negativeComments,
        };

        return $bucket[array_rand($bucket)];
    }
}
