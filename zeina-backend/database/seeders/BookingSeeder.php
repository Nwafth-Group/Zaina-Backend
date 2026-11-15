<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingHistory;
use App\Models\BookingVisit;
use App\Models\CustomerAddress;
use App\Models\DeparturePoint;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\ServiceZone;
use App\Models\Team;
use App\Models\TransportGroup;
use App\Models\TransportGroupWorker;
use App\Models\User;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $services = Service::with('options')->get()->keyBy('service_code');
        $zones = ServiceZone::all()->keyBy('name');
        $departurePoints = DeparturePoint::all();
        $drivers = Driver::where('status', 'active')->get();
        $workers = Worker::where('status', 'active')->get();
        $teams = Team::where('status', 'active')->with('workers')->get();

        if ($services->isEmpty() || $workers->isEmpty() || $departurePoints->isEmpty() || $drivers->isEmpty()) {
            $this->command->error('يرجى التأكد من تشغيل seeders الخدمات والعمال ونقاط الانطلاق والسائقين أولاً.');
            return;
        }

        // 1. Create Real Demo Customers
        $customerRole = Role::where('name', 'Customer')->first();

        $demoCustomersData = [
            [
                'first_name' => 'سارة',
                'last_name' => 'زكي',
                'phone_number' => '+201020220823',
                'email' => 'sara.zaki@demo.com',
                'gender' => 'Female',
                'address' => [
                    'label' => 'المنزل',
                    'zone' => 'منطقة مدينة نصر ومصر الجديدة',
                    'street' => 'شارع مدينة نصر، مبنى فالي، رقم 20، شقة 12',
                    'building_name' => 'مبنى فالي',
                    'building_no' => '20',
                    'floor' => '3',
                    'apartment_no' => '12',
                    'latitude' => 30.0612,
                    'longitude' => 31.3345,
                ],
            ],
            [
                'first_name' => 'إسراء',
                'last_name' => 'عادل زكي',
                'phone_number' => '+201060250823',
                'email' => 'esraa.adel@demo.com',
                'gender' => 'Female',
                'address' => [
                    'label' => 'المنزل',
                    'zone' => 'منطقة مدينة نصر ومصر الجديدة',
                    'street' => 'شارع النزهة، عمارة الأمل 37، شقة 5',
                    'building_name' => 'عمارة الأمل',
                    'building_no' => '37',
                    'floor' => '2',
                    'apartment_no' => '5',
                    'latitude' => 30.0845,
                    'longitude' => 31.3412,
                ],
            ],
            [
                'first_name' => 'أحمد',
                'last_name' => 'ممدوح توفيق',
                'phone_number' => '+201060350823',
                'email' => 'ahmed.mamdouh@demo.com',
                'gender' => 'Male',
                'address' => [
                    'label' => 'الفيلا',
                    'zone' => 'منطقة التجمع الخامس والنرجس',
                    'street' => 'شارع التسعين الجنوبي، فيلا 14، النرجس 3',
                    'building_name' => 'فيلا 14 النرجس',
                    'building_no' => '14',
                    'floor' => '1',
                    'apartment_no' => '1',
                    'latitude' => 30.0154,
                    'longitude' => 31.4589,
                ],
            ],
            [
                'first_name' => 'نورهان',
                'last_name' => 'السيد عمر',
                'phone_number' => '+201012345678',
                'email' => 'nourhan.omar@demo.com',
                'gender' => 'Female',
                'address' => [
                    'label' => 'المنزل',
                    'zone' => 'منطقة الرحاب والتجمع الأول',
                    'street' => 'مدينة الرحاب، مجموعة 124، عمارة 8، شقة 4',
                    'building_name' => 'عمارة 8 الرحاب',
                    'building_no' => '8',
                    'floor' => '2',
                    'apartment_no' => '4',
                    'latitude' => 30.0620,
                    'longitude' => 31.4920,
                ],
            ],
            [
                'first_name' => 'ريم',
                'last_name' => 'إبراهيم خليل',
                'phone_number' => '+201199887766',
                'email' => 'reem.khalil@demo.com',
                'gender' => 'Female',
                'address' => [
                    'label' => 'المنزل',
                    'zone' => 'منطقة المعادي والزهراء',
                    'street' => 'شارع 9، عمارة 42، شقة 8، المعادي',
                    'building_name' => 'برج المعادي 42',
                    'building_no' => '42',
                    'floor' => '4',
                    'apartment_no' => '8',
                    'latitude' => 29.9610,
                    'longitude' => 31.2780,
                ],
            ],
        ];

        $customers = [];
        $addresses = [];

        foreach ($demoCustomersData as $cData) {
            $user = User::updateOrCreate(
                ['phone_number' => $cData['phone_number']],
                [
                    'first_name' => $cData['first_name'],
                    'last_name' => $cData['last_name'],
                    'email' => $cData['email'],
                    'password' => Hash::make('password'),
                    'gender' => $cData['gender'],
                    'type' => 'customer',
                    'verification_status' => 'approved',
                    'status' => 'active',
                    'wallet_balance' => 2500,
                ]
            );

            if ($customerRole) {
                $user->assignRole($customerRole);
            }

            $matchedZone = $zones->get($cData['address']['zone']) ?? $zones->first();

            $addr = CustomerAddress::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'label' => $cData['address']['label'],
                ],
                [
                    'service_zone_id' => $matchedZone?->id,
                    'latitude' => $cData['address']['latitude'],
                    'longitude' => $cData['address']['longitude'],
                    'street' => $cData['address']['street'],
                    'building_name' => $cData['address']['building_name'],
                    'building_no' => $cData['address']['building_no'],
                    'floor' => $cData['address']['floor'],
                    'apartment_no' => $cData['address']['apartment_no'],
                    'contact_name' => $cData['first_name'] . ' ' . $cData['last_name'],
                    'phone_number' => $cData['phone_number'],
                    'notes' => 'الرجاء الاتصال قبل الوصول بـ 15 دقيقة.',
                    'is_default' => true,
                    'is_active' => true,
                    'serviceable_status' => 'serviceable',
                ]
            );

            $customers[] = $user;
            $addresses[$user->id] = $addr;
        }

        // Helper to find qualified worker for a service code
        $getWorker = function ($code) use ($workers, $services) {
            $srv = $services->get($code);
            if (!$srv) return $workers->first();
            return $workers->filter(function ($w) use ($srv) {
                return $w->services->contains('id', $srv->id);
            })->first() ?? $workers->first();
        };

        // Dates
        $todayStr = Carbon::today()->toDateString();
        $tomorrowStr = Carbon::tomorrow()->toDateString();
        $yesterdayStr = Carbon::yesterday()->toDateString();

        // Specific Real Demo Bookings Plan
        $bookingsPlan = [
            // -------------------------------------------------------------
            // TOMORROW - SHIFT 1 (08:00 AM - 12:00 PM) - Primary Dispatch Demo
            // -------------------------------------------------------------
            [
                'ref' => 'HC-773287',
                'customer' => $customers[0], // سارة زكي
                'service_code' => 'PEDICURE_MANICURE',
                'date' => $tomorrowStr,
                'start' => '08:00:00',
                'end' => '10:00:00',
                'status' => 'Upcoming',
                'worker_names' => ['منى فاروق عبد الغني'],
                'driver_name' => 'محمود السيد مصطفى',
                'spot_name' => 'سكن العاملات - مدينة نصر',
                'departure_time' => '07:00 AM',
                'zone_name' => 'منطقة مدينة نصر ومصر الجديدة',
            ],
            [
                'ref' => 'HC-861252',
                'customer' => $customers[0], // سارة زكي
                'service_code' => 'HOSPITALITY_WORKER_HOURLY',
                'date' => $tomorrowStr,
                'start' => '08:00:00',
                'end' => '12:00:00',
                'status' => 'Upcoming',
                'worker_names' => ['نور الهدى طارق شريف', 'هبة فاروق حسنين'],
                'driver_name' => 'أحمد عبد الفتاح علي',
                'spot_name' => 'سكن العاملات - مدينة نصر',
                'departure_time' => '07:15 AM',
                'zone_name' => 'منطقة مدينة نصر ومصر الجديدة',
            ],
            [
                'ref' => 'HC-156610',
                'customer' => $customers[1], // إسراء عادل زكي
                'service_code' => 'BABYSITTER_HOURLY',
                'date' => $tomorrowStr,
                'start' => '08:00:00',
                'end' => '13:00:00',
                'status' => 'Upcoming',
                'worker_names' => ['سلمى يوسف عبد العزيز'],
                'driver_name' => 'محمد إبراهيم حسن',
                'spot_name' => 'مركز انطلاق التجمع - القاهرة الجديدة',
                'departure_time' => '07:00 AM',
                'zone_name' => 'منطقة مدينة نصر ومصر الجديدة',
            ],
            [
                'ref' => 'HC-478136',
                'customer' => $customers[1], // إسراء عادل زكي
                'service_code' => 'DOMESTIC_HELPER_HOURLY',
                'date' => $tomorrowStr,
                'start' => '08:00:00',
                'end' => '12:00:00',
                'status' => 'Upcoming',
                'worker_names' => ['مريم إبراهيم حسن', 'فاطمة أحمد عبد الله'],
                'driver_name' => 'شريف كمال التوني',
                'spot_name' => 'مقر انطلاق الرحاب - القاهرة الجديدة',
                'departure_time' => '07:00 AM',
                'zone_name' => 'منطقة مدينة نصر ومصر الجديدة',
            ],
            [
                'ref' => 'HC-864382',
                'customer' => $customers[2], // أحمد ممدوح توفيق
                'service_code' => 'STAIRS_SURFACES_CLEANING',
                'date' => $tomorrowStr,
                'start' => '09:00:00',
                'end' => '13:00:00',
                'status' => 'Upcoming',
                'worker_names' => ['سامح عبد السلام متولي', 'وحيد فاروق حسنين'],
                'driver_name' => 'خالد عبد المجيد مرسي',
                'spot_name' => 'مركز انطلاق التجمع - القاهرة الجديدة',
                'departure_time' => '08:00 AM',
                'zone_name' => 'منطقة التجمع الخامس والنرجس',
            ],
            [
                'ref' => 'HC-101904',
                'customer' => $customers[3], // نورهان السيد عمر
                'service_code' => 'DEEP_CLEANING',
                'date' => $tomorrowStr,
                'start' => '09:00:00',
                'end' => '15:00:00',
                'status' => 'Upcoming',
                'worker_names' => ['أحمد محمود حسن', 'عبد الله محمد علي'],
                'driver_name' => 'ياسر فتحي زكي',
                'spot_name' => 'مقر انطلاق الرحاب - القاهرة الجديدة',
                'departure_time' => '08:15 AM',
                'zone_name' => 'منطقة الرحاب والتجمع الأول',
            ],

            // -------------------------------------------------------------
            // TODAY - Active in Service & Completed
            // -------------------------------------------------------------
            [
                'ref' => 'HC-319021',
                'customer' => $customers[0], // سارة زكي
                'service_code' => 'DOMESTIC_HELPER_HOURLY',
                'date' => $todayStr,
                'start' => '08:00:00',
                'end' => '12:00:00',
                'status' => 'inService',
                'worker_names' => ['خديجة محمد السيد'],
                'driver_name' => 'محمود السيد مصطفى',
                'spot_name' => 'سكن العاملات - مدينة نصر',
                'departure_time' => '07:00 AM',
                'zone_name' => 'منطقة مدينة نصر ومصر الجديدة',
            ],
            [
                'ref' => 'HC-319022',
                'customer' => $customers[2], // أحمد ممدوح توفيق
                'service_code' => 'SOFA_CLEANING',
                'date' => $todayStr,
                'start' => '09:00:00',
                'end' => '12:00:00',
                'status' => 'inService',
                'worker_names' => ['حازم نبيل بدوي', 'إسلام سعيد عاشور'],
                'driver_name' => 'طارق عبد الوهاب رشاد',
                'spot_name' => 'مركز انطلاق التجمع - القاهرة الجديدة',
                'departure_time' => '08:00 AM',
                'zone_name' => 'منطقة التجمع الخامس والنرجس',
            ],
            [
                'ref' => 'HC-319023',
                'customer' => $customers[4], // ريم إبراهيم خليل
                'service_code' => 'PEDICURE_MANICURE',
                'date' => $todayStr,
                'start' => '10:00:00',
                'end' => '12:00:00',
                'status' => 'completed',
                'worker_names' => ['رحمة ماهر كمال'],
                'driver_name' => 'عمرو حسام الدين فؤاد',
                'spot_name' => 'نقطة انطلاق المعادي - زهراء المعادي',
                'departure_time' => '09:00 AM',
                'zone_name' => 'منطقة المعادي والزهراء',
            ],
            [
                'ref' => 'HC-319024',
                'customer' => $customers[3], // نورهان السيد عمر
                'service_code' => 'AC_CLEANING',
                'date' => $todayStr,
                'start' => '11:00:00',
                'end' => '13:00:00',
                'status' => 'completed',
                'worker_names' => ['مدحت عبد اللطيف إبراهيم', 'أشرف زكي سليمان'],
                'driver_name' => 'هاني سليمان عبد الهادي',
                'spot_name' => 'مقر انطلاق الرحاب - القاهرة الجديدة',
                'departure_time' => '10:00 AM',
                'zone_name' => 'منطقة الرحاب والتجمع الأول',
            ],

            // -------------------------------------------------------------
            // YESTERDAY - Completed
            // -------------------------------------------------------------
            [
                'ref' => 'HC-210941',
                'customer' => $customers[0], // سارة زكي
                'service_code' => 'HOSPITALITY_WORKER_HOURLY',
                'date' => $yesterdayStr,
                'start' => '09:00:00',
                'end' => '13:00:00',
                'status' => 'completed',
                'worker_names' => ['آية سامي يوسف'],
                'driver_name' => 'محمود السيد مصطفى',
                'spot_name' => 'سكن العاملات - مدينة نصر',
                'departure_time' => '08:00 AM',
                'zone_name' => 'منطقة مدينة نصر ومصر الجديدة',
            ],
        ];

        DB::beginTransaction();
        try {
            // Track transport groups by date + start_time + driver_id
            $transportGroupsMap = [];

            foreach ($bookingsPlan as $bData) {
                $service = $services->get($bData['service_code']);
                if (!$service) continue;

                $customer = $bData['customer'];
                $address = $addresses[$customer->id] ?? CustomerAddress::where('user_id', $customer->id)->first();
                if (!$address) continue;

                $durationMinutes = abs((int) Carbon::parse($bData['end'])->diffInMinutes(Carbon::parse($bData['start']))) ?: 180;

                // Resolve workers by name
                $assignedWorkers = collect();
                foreach ($bData['worker_names'] as $wName) {
                    $foundWorker = $workers->firstWhere('name', $wName);
                    if ($foundWorker) {
                        $assignedWorkers->push($foundWorker);
                    }
                }
                if ($assignedWorkers->isEmpty()) {
                    $fallback = $getWorker($bData['service_code']);
                    if ($fallback) $assignedWorkers->push($fallback);
                }

                $leadWorker = $assignedWorkers->first();
                $workerGender = $leadWorker ? $leadWorker->gender : 'Female';

                $subtotal = 200.00;
                $taxAmount = 20.00;
                $totalAmount = 220.00;

                $booking = Booking::updateOrCreate(
                    ['booking_ref' => $bData['ref']],
                    [
                        'customer_id'       => $customer->id,
                        'service_id'        => $service->id,
                        'address_id'        => $address->id,
                        'worker_id'         => $leadWorker?->id,
                        'team_id'           => null,
                        'booking_date'      => $bData['date'],
                        'start_time'        => $bData['start'],
                        'end_time'          => $bData['end'],
                        'duration_minutes'  => $durationMinutes,
                        'price_subtotal'    => $subtotal,
                        'tax_amount'        => $taxAmount,
                        'discount_amount'   => 0.00,
                        'total_amount'      => $totalAmount,
                        'status'            => $bData['status'],
                        'payment_status'    => 'paid',
                        'payment_method'    => 'cash',
                        'number_of_workers' => $assignedWorkers->count(),
                        'worker_gender'     => $workerGender,
                    ]
                );

                // Assign workers in booking_assignments
                BookingAssignment::where('booking_id', $booking->id)->delete();
                foreach ($assignedWorkers as $w) {
                    BookingAssignment::create([
                        'booking_id' => $booking->id,
                        'worker_id'  => $w->id,
                        'status'     => 'active',
                    ]);
                }

                // Booking Visit
                BookingVisit::updateOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'visit_date' => $bData['date'],
                        'start_time' => $bData['start'],
                        'end_time'   => $bData['end'],
                        'status'     => $bData['status'],
                    ]
                );

                // Booking History
                BookingHistory::firstOrCreate(
                    [
                        'booking_id' => $booking->id,
                        'new_status' => $bData['status'],
                    ],
                    [
                        'note'       => 'حجز عرض توضيحي واقعي متكامل.',
                        'created_by' => $customer->id,
                    ]
                );

                // -------------------------------------------------------------
                // Transport Dispatch Linking (for Tomorrow & Today)
                // -------------------------------------------------------------
                if (in_array($bData['date'], [$todayStr, $tomorrowStr])) {
                    $driver = $drivers->firstWhere('name', $bData['driver_name']) ?? $drivers->first();
                    $spot = $departurePoints->firstWhere('name', $bData['spot_name']) ?? $departurePoints->first();
                    $zone = $zones->get($bData['zone_name']) ?? $zones->first();

                    $groupKey = $bData['date'] . '_' . substr($bData['start'], 0, 5) . '_' . ($driver ? $driver->id : 0);

                    if (!isset($transportGroupsMap[$groupKey])) {
                        $group = TransportGroup::firstOrCreate(
                            [
                                'service_date' => $bData['date'],
                                'start_time'   => substr($bData['start'], 0, 5),
                                'driver_id'    => $driver?->id,
                            ],
                            [
                                'governorate'        => $zone?->governorate ?? 'القاهرة',
                                'service_zone_id'    => $zone?->id,
                                'area_name'          => $zone?->city ?? 'مدينة نصر',
                                'departure_point_id' => $spot?->id,
                                'departure_time'     => $bData['departure_time'],
                                'vehicle_type'       => $driver?->vehicle_type ?? 'Toyota HiAce (فان)',
                                'vehicle_plate'      => $driver?->vehicle_plate ?? 'أ ب ج 1234',
                                'vehicle_capacity'   => $driver?->vehicle_capacity ?? 6,
                                'notes'              => 'رحلة انطلاق مجدولة ومؤكدة للشفت الصباحي.',
                                'status'             => 'ready_for_transport',
                            ]
                        );

                        $transportGroupsMap[$groupKey] = $group;
                    } else {
                        $group = $transportGroupsMap[$groupKey];
                    }

                    // Attach booking to transport group
                    $group->bookings()->syncWithoutDetaching([$booking->id => ['dropoff_sequence' => 1]]);

                    // Attach each worker to transport_group_workers
                    foreach ($assignedWorkers as $idx => $w) {
                        TransportGroupWorker::updateOrCreate(
                            [
                                'transport_group_id' => $group->id,
                                'booking_id'         => $booking->id,
                                'worker_id'          => $w->id,
                            ],
                            [
                                'dropoff_sequence'   => $idx + 1,
                                'departure_point_id' => $spot?->id,
                                'departure_time'     => $bData['departure_time'],
                                'status'             => 'assigned',
                                'notes'              => 'تم تأكيد التعيين والانطلاق.',
                            ]
                        );
                    }
                }
            }

            DB::commit();
            $this->command->info('تم إنشاء حجوزات العرض التوضيحي وجداول النقل والتوزيع الميداني الحقيقية بنجاح!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('حدث خطأ أثناء تشغيل BookingSeeder: ' . $e->getMessage());
        }
    }
}
