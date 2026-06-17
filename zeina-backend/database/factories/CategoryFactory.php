<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => fake()->unique()->words(2, true),
            'name_en' => fake()->unique()->words(2, true),
            'parent_id' => null,
            'status' => 'active',
        ];
    }

    /**
     * Make this category a subcategory of the given top-level parent.
     */
    public function childOf(Category $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * Indicate that the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'inactive',
        ]);
    }
}
