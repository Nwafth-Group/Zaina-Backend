<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Service;
use App\Models\Worker;
use App\Models\CustomerAddress;
use App\Models\Booking;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        // جلب بيانات أساسية لربط الحجز بها
       $customer = User::where('email', 'sara@example.com')->first();
        
        // Fallback: لو بالغلط انحذفت سارة، جيب أي عميل
        if (!$customer) {
            $customer = User::where('type', 'customer')->first();
        }
        $service = Service::first();
        
        // إذا ما عندك جدول عمال أو عناوين لسا، ممكن تعمل Fake مؤقت
        // نفترض إنك عاملهم:
        $address = CustomerAddress::where('user_id', $customer->id)->first();
        $worker = Worker::first();

        if (!$customer || !$service || !$address) {
            $this->command->info('يرجى التأكد من وجود User و Service و Address في الداتابيز أولاً.');
            return;
        }

        $statuses = ['Upcoming', 'inService', 'completed', 'cancelled'];
        
        foreach ($statuses as $index => $status) {
            // تواريخ منطقية بناءً على الحالة
            if ($status == 'Upcoming') {
                $date = Carbon::now()->addDays(2);
            } elseif ($status == 'completed' || $status == 'cancelled') {
                $date = Carbon::now()->subDays(5);
            } else { // inService
                $date = Carbon::now();
            }

            $booking = Booking::create([
                'booking_ref' => 'HC-' . rand(100000, 999999),
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'address_id' => $address->id,
                'worker_id' => $worker->id ?? null,
                'booking_date' => $date->format('Y-m-d'),
                'start_time' => '10:00:00',
                'end_time' => '13:00:00', // 3 ساعات
                'duration_minutes' => 180,
                'price_subtotal' => 120.00,
                'tax_amount' => 10.00,
                'discount_amount' => 0,
                'total_amount' => 130.00,
                'status' => $status,
                'payment_status' => $status == 'completed' ? 'paid' : 'unpaid',
            ]);

            // إضافة سجل في جدول الـ History اللي عملناه سابقاً
            \App\Models\BookingHistory::create([
                'booking_id' => $booking->id,
                'new_status' => $status,
                'note' => 'تم إنشاؤه بواسطة Seeder',
                'created_by' => $customer->id,
            ]);
        }
    }
}