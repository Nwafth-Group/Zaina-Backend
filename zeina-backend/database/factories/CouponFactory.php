<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word() . ' Coupon',
            'code' => strtoupper($this->faker->unique()->bothify('??##')),
            'type' => $this->faker->randomElement(['fixed', 'percentage']),
            'value' => $this->faker->randomFloat(2, 5, 50),
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(30),
            'usage_limit' => 100,
            'user_usage_limit' => 2,
            'is_for_all_users' => true,
            'is_for_all_services' => true,
            'status' => 'active',
        ];
    }
}
