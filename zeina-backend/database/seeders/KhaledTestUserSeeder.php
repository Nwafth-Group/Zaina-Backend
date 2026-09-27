<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\Worker;
use App\Models\Team;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingHistory;
use App\Models\BookingVisit;
use App\Models\ServiceZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KhaledTestUserSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Ensure TeamSeeder has been run if no teams exist
        if (Team::count() === 0) {
            $this->call(TeamSeeder::class);
        }

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
                'wallet_balance'      => 3000
            ]
        );

        // Assign Customer role if not assigned
        $customerRole = Role::where('name', 'Customer')->first();
        if ($customerRole) {
            $user->assignRole($customerRole);
        }

        // Fetch matching or default Service Zone
        $serviceZone = ServiceZone::where('name', 'LIKE', '%Abbas%')
            ->orWhere('name', 'LIKE', '%عباس%')
            ->first() ?? ServiceZone::first();

        // 2. Create Address
        $address = CustomerAddress::updateOrCreate(
            [
                'user_id' => $user->id,
                'label'   => 'Home 2',
            ],
            [
                'service_zone_id'    => $serviceZone?->id,
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

        // Standard hourly services (IDs 1, 2, 3)
        $standardServices = Service::whereIn('id', [1, 2, 3])->where('status', 'active')->get();
        if ($standardServices->isEmpty()) {
            $standardServices = Service::where('status', 'active')->take(3)->get();
        }

        // Specialized services (Services 6 to 11)
        $specializedServices = Service::with('options')->whereIn('id', [6, 7, 8, 9, 10, 11])->where('status', 'active')->get();
        if ($specializedServices->isEmpty()) {
            $specializedServices = Service::with('options')->whereHas('teams')->get();
        }

        $activeTeams = Team::with('workers')->where('status', 'active')->get();

        // 3. Generate 18 Bookings (6 Upcoming, 6 inService, 6 completed)
        // Each status gets 3 standard bookings and 3 specialized bookings with team & options
        $statusGroups = [
            'Upcoming'  => 6,
            'inService' => 6,
            'completed' => 6,
        ];

        DB::beginTransaction();
        try {
            // Clean up previous test bookings for Khaled
            $oldBookingIds = Booking::where('customer_id', $user->id)->pluck('id');
            if ($oldBookingIds->isNotEmpty()) {
                BookingAssignment::whereIn('booking_id', $oldBookingIds)->delete();
                BookingVisit::whereIn('booking_id', $oldBookingIds)->delete();
                BookingHistory::whereIn('booking_id', $oldBookingIds)->delete();
                Booking::whereIn('id', $oldBookingIds)->delete();
            }

            foreach ($statusGroups as $status => $totalCount) {
                for ($i = 0; $i < $totalCount; $i++) {
                    $isSpecialized = ($i % 2 === 1); // Alternating between standard and specialized

                    if ($isSpecialized && $specializedServices->isNotEmpty() && $activeTeams->isNotEmpty()) {
                        // --- SPECIALIZED SERVICE WITH TEAM & OPTIONS ---
                        $service = $specializedServices->values()->get(($i / 2) % $specializedServices->count());
                        
                        // Select an active team linked to this service, or pick from active teams
                        $team = $service->teams()->where('teams.status', 'active')->with('workers')->first()
                            ?? $activeTeams->get($i % $activeTeams->count());

                        $teamWorkers = $team->workers()->where('status', 'active')->get();
                        if ($teamWorkers->isEmpty()) {
                            $teamWorkers = $team->workers;
                        }
                        $leadWorker = $teamWorkers->first();

                        // Pick 1 to 2 options from this service
                        $optionsPool = $service->options->where('status', 'active');
                        if ($optionsPool->isEmpty()) {
                            $optionsPool = ServiceOption::where('service_id', $service->id)->get();
                        }

                        $selectedOptions = [];
                        $subtotal = 0;
                        if ($optionsPool->isNotEmpty()) {
                            $picked = $optionsPool->take(2);
                            foreach ($picked as $pIdx => $opt) {
                                $qty = ($pIdx === 0) ? 2 : 1;
                                $unitPrice = (float) $opt->unit_price;
                                $totalItem = $unitPrice * $qty;
                                $subtotal += $totalItem;
                                $selectedOptions[] = [
                                    'id'          => $opt->id,
                                    'name_ar'     => $opt->name_ar,
                                    'name_en'     => $opt->name_en,
                                    'unit_price'  => $unitPrice,
                                    'quantity'    => $qty,
                                    'total_price' => $totalItem,
                                ];
                            }
                        } else {
                            $subtotal = 350.00;
                        }

                        $taxAmount = round($subtotal * 0.15, 2);
                        $totalAmount = $subtotal + $taxAmount;

                        $workerCount = $teamWorkers->count() ?: 3;
                        $workerGender = 'Male';
                        $assignedWorkers = $teamWorkers;
                        $teamId = $team->id;
                    } else {
                        // --- STANDARD SERVICE (SINGLE WORKER) ---
                        $service = $standardServices->values()->get($i % $standardServices->count());

                        $worker = Worker::whereHas('services', function ($q) use ($service) {
                            $q->where('services.id', $service->id);
                        })->where('status', 'active')->first();

                        if (!$worker) {
                            $worker = Worker::where('status', 'active')->first();
                        }

                        $subtotal = 200.00;
                        $taxAmount = 20.00;
                        $totalAmount = 220.00;
                        $selectedOptions = null;
                        $workerCount = 1;
                        $workerGender = $worker?->gender ?? 'Female';
                        $leadWorker = $worker;
                        $assignedWorkers = $worker ? collect([$worker]) : collect();
                        $teamId = null;
                    }

                    // Logical dates & times
                    if ($status === 'Upcoming') {
                        $date = Carbon::now()->addDays($i + 1);
                        $startTime = sprintf('%02d:00:00', 9 + ($i % 3) * 3);
                        $endTime = sprintf('%02d:00:00', 12 + ($i % 3) * 3);
                    } elseif ($status === 'inService') {
                        $date = Carbon::now();
                        $startTime = sprintf('%02d:00:00', 8 + ($i % 4) * 2);
                        $endTime = sprintf('%02d:00:00', 12 + ($i % 4) * 2);
                    } else { // completed
                        $date = Carbon::now()->subDays($i + 1);
                        $startTime = sprintf('%02d:00:00', 9 + ($i % 3) * 3);
                        $endTime = sprintf('%02d:00:00', 13 + ($i % 3) * 3);
                    }

                    $durationMinutes = abs((int) Carbon::parse($endTime)->diffInMinutes(Carbon::parse($startTime))) ?: 180;

                    $bookingRef = 'KB-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                    while (Booking::where('booking_ref', $bookingRef)->exists()) {
                        $bookingRef = 'KB-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                    }

                    $booking = Booking::create([
                        'booking_ref'       => $bookingRef,
                        'customer_id'       => $user->id,
                        'service_id'        => $service->id,
                        'address_id'        => $address->id,
                        'worker_id'         => $leadWorker?->id,
                        'team_id'           => $teamId,
                        'booking_date'      => $date->toDateString(),
                        'start_time'        => $startTime,
                        'end_time'          => $endTime,
                        'duration_minutes'  => $durationMinutes,
                        'price_subtotal'    => $subtotal,
                        'tax_amount'        => $taxAmount,
                        'discount_amount'   => 0.00,
                        'total_amount'      => $totalAmount,
                        'status'            => $status,
                        'payment_status'    => 'paid',
                        'payment_method'    => 'cash',
                        'number_of_workers' => $workerCount,
                        'worker_gender'     => $workerGender,
                        'selected_options'  => $selectedOptions,
                    ]);

                    // Assign workers (for Team: all team members; for Standard: single worker)
                    foreach ($assignedWorkers as $w) {
                        BookingAssignment::create([
                            'booking_id' => $booking->id,
                            'worker_id'  => $w->id,
                            'status'     => 'active',
                        ]);
                    }

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
                        'note'       => $teamId ? "Seeded specialized booking with team #{$teamId}." : 'Seeded test booking for Khaled.',
                        'created_by' => $user->id,
                    ]);
                }
            }

            DB::commit();
            $this->command->info("Seeded 18 test bookings (standard and specialized with teams & options) for Khaled (+201012935668) successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Failed to seed Khaled bookings: " . $e->getMessage());
        }
    }
}
