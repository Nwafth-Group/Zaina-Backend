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
        $firstNames = ['أحمد', 'محمد', 'محمود', 'علي', 'عمر', 'سارة', 'فاطمة', 'مريم', 'نورهان', 'إسراء'];
        $lastNames = ['السيد', 'علي', 'حسن', 'إبراهيم', 'مصطفى', 'محمود', 'عثمان', 'عبد الله', 'خليل'];
        $statuses = ['active', 'active', 'inactive'];
        $verificationStatuses = ['pending', 'approved', 'rejected'];
        $genders = ['Male', 'Female'];
        $languages = ['ar', 'en'];

        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        $phone = '01' . ['0', '1', '2', '5'][array_rand(['0', '1', '2', '5'])] . mt_rand(10000000, 99999999);
        $email = 'user_' . Str::random(8) . '@example.com';

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone_number' => $phone,
            'email' => $email,
            'password' => static::$password ??= Hash::make('password'),
            'verification_status' => $verificationStatuses[array_rand($verificationStatuses)],
            'status' => $statuses[array_rand($statuses)],
            'gender' => $genders[array_rand($genders)],
            'preferred_language' => $languages[array_rand($languages)],
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
