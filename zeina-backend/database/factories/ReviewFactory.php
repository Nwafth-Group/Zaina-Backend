<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'customer_id' => User::factory()->customer(),
            'reviewable_type' => Worker::class,
            'reviewable_id' => Worker::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
        ];
    }

    /**
     * Attach the review to an existing worker.
     */
    public function forWorker(Worker $worker): static
    {
        return $this->state(fn (array $attributes): array => [
            'reviewable_type' => Worker::class,
            'reviewable_id' => $worker->id,
        ]);
    }
}
