<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Worker>
 */
class WorkerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nationalities = ['Bangladesh', 'Philippines', 'India', 'Vietnam', 'Egypt', 'Palestine'];

        return [
            'name' => fake()->name(), // اسم عشوائي
            'nationality' => fake()->randomElement($nationalities), // جنسية عشوائية من المصفوفة
            'rating' => fake()->randomFloat(1, 3.5, 5.0), // تقييم عشوائي من 3.5 إلى 5.0 (رقم عشري واحد)
            // 'status' => fake()->, // 85% من العمال راح يكونوا متاحين (true) والباقي غير متاحين
        ];
    }
}
