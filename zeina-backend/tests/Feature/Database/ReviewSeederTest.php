<?php

namespace Tests\Feature\Database;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Service;
use App\Models\Worker;
use Database\Seeders\ReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_worker_and_service_reviews_and_recomputes_averages(): void
    {
        $service = Service::factory()->create(['avg_rating' => 0]);
        $worker = Worker::factory()->create(['avg_rating' => 0]);

        // 20 completed bookings make it near-certain the ~75% sampling reviews some.
        for ($i = 0; $i < 20; $i++) {
            $booking = Booking::factory()->create([
                'service_id' => $service->id,
                'status' => 'completed',
            ]);
            $booking->workers()->attach($worker->id, ['status' => 'active']);
        }

        $this->seed(ReviewSeeder::class);

        $this->assertDatabaseHas('reviews', [
            'reviewable_type' => Service::class,
            'reviewable_id' => $service->id,
        ]);
        $this->assertDatabaseHas('reviews', [
            'reviewable_type' => Worker::class,
            'reviewable_id' => $worker->id,
        ]);

        // Every seeded rating is between 1 and 5.
        $this->assertEquals(0, Review::where('rating', '<', 1)->orWhere('rating', '>', 5)->count());

        // avg_rating mirrors the actual review average for the reviewed worker/service.
        $expectedWorkerAvg = round((float) Review::where('reviewable_type', Worker::class)
            ->where('reviewable_id', $worker->id)->avg('rating'), 2);
        $this->assertGreaterThan(0, $expectedWorkerAvg);
        $this->assertEqualsWithDelta($expectedWorkerAvg, (float) $worker->fresh()->avg_rating, 0.001);

        $expectedServiceAvg = round((float) Review::where('reviewable_type', Service::class)
            ->where('reviewable_id', $service->id)->avg('rating'), 2);
        $this->assertEqualsWithDelta($expectedServiceAvg, (float) $service->fresh()->avg_rating, 0.001);
    }

    public function test_it_does_nothing_when_there_are_no_completed_bookings(): void
    {
        Booking::factory()->create(['status' => 'Upcoming']);

        $this->seed(ReviewSeeder::class);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_each_completed_booking_yields_at_most_one_review_per_reviewable(): void
    {
        $service = Service::factory()->create();
        $worker = Worker::factory()->create();
        $booking = Booking::factory()->create(['service_id' => $service->id, 'status' => 'completed']);
        $booking->workers()->attach($worker->id, ['status' => 'active']);

        // Running twice must not violate the unique (booking, reviewable) constraint.
        $this->seed(ReviewSeeder::class);
        $this->seed(ReviewSeeder::class);

        $this->assertLessThanOrEqual(1, Review::where('booking_id', $booking->id)
            ->where('reviewable_type', Service::class)->count());
        $this->assertLessThanOrEqual(1, Review::where('booking_id', $booking->id)
            ->where('reviewable_type', Worker::class)->where('reviewable_id', $worker->id)->count());
    }
}
