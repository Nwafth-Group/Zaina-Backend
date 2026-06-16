<?php

namespace Database\Factories;

use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_ref' => 'BKG-'.fake()->unique()->numerify('2026-#####'),
            'customer_id' => User::factory()->customer(),
            'service_id' => Service::factory(),
            'address_id' => CustomerAddress::factory(),
            'booking_date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '13:00:00',
            'duration_minutes' => 180,
            'price_subtotal' => 300,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 300,
            'status' => 'Upcoming',
            'payment_status' => 'unpaid',
        ];
    }
}
