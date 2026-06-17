<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Country>
 */
class CountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => fake()->country(),
            'name_en' => fake()->country(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'currency_code' => 'EGP',
            'phone_code' => '+20',
            'status' => 'active',
        ];
    }
}
