<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\Worker;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingHistory;
use App\Models\BookingVisit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KhaledTestUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create or fetch user Khaled
        $user = User::updateOrCreate(
            ['phone_number' => '+201012935668'],
            [
                'first_name'          => 'khaled',
                'last_name'           => 'mohammed',
                'email'               => 'khaled1@gmail.com',
                'password'            => Hash::make('password'),
                'gender'              => 'Male',
                'id_number'           => '4055169232',
                'type'                => 'customer',
                'verification_status' => 'approved',
                'status'              => 'active',
            ]
        );

        // Assign Customer role if not assigned
        $customerRole = Role::where('name', 'Customer')->first();
        if ($customerRole) {
            $user->assignRole($customerRole);
        }

        // 2. Create Address
        $address = CustomerAddress::updateOrCreate(
            [
                'user_id' => $user->id,
                'label'   => 'Home 2',
            ],
            [
                'area_id'            => 2,
                'latitude'           => 30.0583,
                'longitude'          => 31.3361,
                'street'             => 'Abbas El Akkad Street 2 ',
                'building_name'      => 'Al Noor Building 2 ',
                'building_no'        => '24',
                'floor'              => '5',
                'apartment_no'       => '12',
                'contact_name'       => 'Saeed Ali Al-Omar',
                'phone_number'       => '+201012345678',
                'secondary_phone'    => '+201122334455',
                'notes'              => 'الرجاء الاتصال قبل الوصول بـ 10 دقائق.',
                'is_default'         => true,
                'is_active'          => true,
                'serviceable_status' => 'serviceable',
            ]
        );

        // Ensure this is the only default address for the user
        CustomerAddress::where('user_id', $user->id)
            ->where('id', '!=', $address->id)
            ->update(['is_default' => false]);

        // Get some active services (preferably 1, 2, 3 as requested)
        $services = Service::whereIn('id', [1, 2, 3])->where('status', 'active')->get();
        if ($services->isEmpty()) {
            $services = Service::where('status', 'active')->take(3)->get();
        }

        if ($services->isEmpty()) {
            $this->command->error('No active services found. Seed CategoryAndServiceSeeder first.');
            return;
        }

        // 3. Generate 15 Bookings (5 Upcoming, 5 inService, 5 completed)
        $statusCounts = [
            'Upcoming'  => 5,
            'inService' => 5,
            'completed' => 5
        ];

        $bookingIndex = 1;

        DB::beginTransaction();
        try {
            foreach ($statusCounts as $status => $count) {
                for ($i = 0; $i < $count; $i++) {
                    // Pick service sequentially
                    $service = $services->values()->get($i % $services->count());
                    
                    // Fetch an active worker for this service
                    $worker = Worker::whereHas('services', function($q) use ($service) {
                        $q->where('services.id', $service->id);
                    })->where('status', 'active')->first();

                    if (!$worker) {
                        // Fallback to any active worker
                        $worker = Worker::where('status', 'active')->first();
                    }

                    if (!$worker) {
                        $this->command->error('No active workers found. Seed WorkerSeeder first.');
                        DB::rollBack();
                        return;
                    }

                    // Generate logical dates
                    if ($status === 'Upcoming') {
                        $date = Carbon::now()->addDays($i + 2); // 2 to 6 days in future
                    } elseif ($status === 'inService') {
                        $date = Carbon::now(); // Today
                    } else { // completed
                        $date = Carbon::now()->subDays($i + 1); // 1 to 5 days in past
                    }

                    // Schedule shifts (e.g. 09:00 AM to 01:00 PM)
                    $startTime = '09:00:00';
                    $endTime = '13:00:00';
                    $durationMinutes = 240;

                    $booking = Booking::create([
                        'booking_ref'      => 'KB-' . str_pad($bookingIndex, 6, '0', STR_PAD_LEFT),
                        'customer_id'      => $user->id,
                        'service_id'       => $service->id,
                        'address_id'       => $address->id,
                        'worker_id'        => $worker->id,
                        'booking_date'     => $date->toDateString(),
                        'start_time'       => $startTime,
                        'end_time'         => $endTime,
                        'duration_minutes' => $durationMinutes,
                        'price_subtotal'   => 200.00,
                        'tax_amount'       => 20.00,
                        'discount_amount'  => 0.00,
                        'total_amount'     => 220.00,
                        'status'           => $status,
                        'payment_status'   => 'paid',
                        'payment_method'   => 'cash',
                        'number_of_workers'=> 1,
                        'worker_gender'    => $worker->gender ?? 'Female',
                    ]);

                    // Assignment
                    BookingAssignment::create([
                        'booking_id' => $booking->id,
                        'worker_id'  => $worker->id,
                        'status'     => 'active',
                    ]);

                    // Visit
                    BookingVisit::create([
                        'booking_id' => $booking->id,
                        'visit_date' => $date->toDateString(),
                        'start_time' => $startTime,
                        'end_time'   => $endTime,
                        'status'     => $status,
                    ]);

                    // History log
                    BookingHistory::create([
                        'booking_id' => $booking->id,
                        'new_status' => $status,
                        'note'       => 'Seeded test booking for Khaled.',
                        'created_by' => $user->id,
                    ]);

                    $bookingIndex++;
                }
            }

            DB::commit();
            $this->command->info("Seeded 15 test bookings for Khaled (+201012935668) successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Failed to seed Khaled bookings: " . $e->getMessage());
        }
    }
}
