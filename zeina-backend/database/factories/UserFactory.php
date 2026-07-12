<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            // توليد رقم موبايل سعودي/فلسطيني وهمي فريد من 10 خانات
            'phone_number' => fake()->unique()->numerify('05########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'verification_status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'status' => fake()->randomElement(['active', 'active', 'inactive']), // نسبة الـ active أعلى
            'gender' => fake()->randomElement(['Male', 'Female']),
            'preferred_language' => fake()->randomElement(['ar', 'en']),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A super admin employee (passes the IsAdmin middleware).
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'super_admin',
            'status' => 'active',
        ]);
    }

    /**
     * An operations employee (passes the IsAdmin middleware).
     */
    public function operation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'operation',
            'status' => 'active',
        ]);
    }

    /**
     * A customer (does NOT pass the IsAdmin middleware).
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'customer',
            'status' => 'active',
        ]);
    }
}
