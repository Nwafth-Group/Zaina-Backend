<?php

namespace Database\Factories;

use App\Models\MobileNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

class MobileNotificationFactory extends Factory
{
    protected $model = MobileNotification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title'      => fake()->sentence(3),
            'title_en'   => 'English Push Title',
            'title_ar'   => 'عنوان الإشعار باللغة العربية',
            'content_en' => 'English Push Content details.',
            'content_ar' => 'تفاصيل محتوى الإشعار باللغة العربية',
            'audience'   => 'all',
            'send_date'  => now()->addDays(2)->format('Y-m-d'),
            'send_time'  => '12:00',
            'platforms'  => ['ios', 'android'],
            'status'     => 'scheduled',
        ];
    }
}
