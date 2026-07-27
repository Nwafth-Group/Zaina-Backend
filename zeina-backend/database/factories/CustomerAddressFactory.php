<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service_zone_id' => null,
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'label' => fake()->randomElement(['Home', 'Office']),
            'street' => fake()->streetName(),
            'phone_number' => fake()->numerify('05########'),
            'serviceable_status' => 'serviceable',
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
