<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ad>
 */
class AdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title_en' => fake()->sentence(3),
            'title_ar' => 'عنوان إعلان تجريبي',
            'type' => 'general',
            'text_en' => fake()->paragraph(),
            'text_ar' => 'محتوى نصي للإعلان التجريبي',
            'link_type' => 'external',
            'link' => fake()->url(),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'inactive',
        ]);
    }
}
