<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingHistory;
use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\User;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. جلب البيانات الأساسية
        $customers = User::where('type', 'customer')->get();
        $services = Service::with(['workers', 'schedules', 'duration'])->get();

        if ($customers->isEmpty() || $services->isEmpty()) {
            $this->command->error('يجب أن يكون لديك عملاء وخدمات مسجلة لتشغيل الـ Seeder.');
            return;
        }

        DB::beginTransaction();
        try {
            // =========================================================
            // الجزء الأول: بناء بيئة اختبار التضاربات للشفتين (Test Bed)
            // =========================================================
            $testCustomer = $customers->first(); 
            $testAddress = CustomerAddress::where('user_id', $testCustomer->id)->first();

            foreach ($services as $service) {
                $activeDays = $service->schedules->where('is_active', true)->pluck('day')->map(fn($d) => strtolower($d))->toArray();
                if (empty($activeDays)) continue;

                // إيجاد أقرب يوم عمل فعال قادم
                $testDate = Carbon::tomorrow();
                while (!in_array(strtolower($testDate->format('l')), $activeDays)) {
                    $testDate->addDay();
                }
                $validTestDateStr = $testDate->format('Y-m-d');

                $periodHours = $service->duration->service_period_hours ?? 2;
                $testWorkers = $service->workers->take(3);
                
                if ($testWorkers->count() >= 2 && $testAddress) {
                    // --- سيناريو الشفت الصباحي ---
                    // نغلق الكتلة الأولى (09:00 إلى +طول الفترة) للعامل الأول
                    $morningStart = Carbon::parse('09:00');
                    $morningEnd   = $morningStart->copy()->addHours($periodHours);
                    $this->createBooking($testCustomer, $service, $testWorkers[0], $testAddress, $validTestDateStr, $morningStart->format('H:i'), $morningEnd->format('H:i'), 'Upcoming');

                    // --- سيناريو الشفت المسائي الجديد 🌙 ---
                    // نغلق الكتلة الأولى في الشفت المسائي (18:00 إلى +طول الفترة) لنفس العامل لعمل تضارب مسائي
                    $eveningStart = Carbon::parse('18:00');
                    $eveningEnd   = $eveningStart->copy()->addHours($periodHours);
                    $this->createBooking($testCustomer, $service, $testWorkers[0], $testAddress, $validTestDateStr, $eveningStart->format('H:i'), $eveningEnd->format('H:i'), 'Upcoming');

                    // حجز كتلة متقاطعة للعامل الثاني في الشفت المسائي لضمان تنوع قائمة المتاحين
                    $eveningBlock2Start = $eveningStart->copy()->addHours($periodHours);
                    $eveningBlock2End   = $eveningBlock2Start->copy()->addHours($periodHours);
                    
                    // نتأكد فقط أنها لا تتعدى نهاية دوام الشفت المسائي (23:00)
                    if ($eveningBlock2End->format('H:i') <= '23:00') {
                        $this->createBooking($testCustomer, $service, $testWorkers[1], $testAddress, $validTestDateStr, $eveningBlock2Start->format('H:i'), $eveningBlock2End->format('H:i'), 'Upcoming');
                    }

                    $this->command->info("[فحص الشفتات] تم بناء تضاربات صباحية ومسائية لخدمة ({$service->name_en}) بيوم {$validTestDateStr}.");
                }
            }

            // =========================================================
            // الجزء الثاني: توزيع حجوزات واقعية وشاملة لـ "جميع" العملاء
            // =========================================================
            $statuses = ['completed', 'cancelled', 'inService', 'Upcoming'];

            foreach ($customers as $customer) {
                // التأكد من وجود عنوان للعميل متناسق مع جغرافية التسعير
                $address = CustomerAddress::where('user_id', $customer->id)->first();
                if (!$address) {
                    $address = CustomerAddress::create([
                        'user_id' => $customer->id,
                        'area_id' => 1,
                        'street'  => 'Automated Seeder Street',
                        'type'    => 'apartment'
                    ]);
                }

                // ننشئ لكل عميل في النظام 5 حجوزات متنوعة لتغطية كافة الحالات التاريخية والمستقبلية
                for ($i = 0; $i < 5; $i++) {
                    $randomService = $services->random();
                    $activeDays = $randomService->schedules->where('is_active', true)->pluck('day')->map(fn($d) => strtolower($d))->toArray();
                    
                    if (empty($activeDays) || $randomService->workers->isEmpty()) continue;

                    $randomWorker = $randomService->workers->random();
                    $randomStatus = $statuses[array_rand($statuses)];
                    $periodHours  = $randomService->duration->service_period_hours ?? 2;
                    
                    // توليد تاريخ منطقي بناءً على حالة الحجز
                    if ($randomStatus === 'Upcoming') {
                        $date = Carbon::now()->addDays(rand(2, 14));
                    } elseif (in_array($randomStatus, ['completed', 'cancelled'])) {
                        $date = Carbon::now()->subDays(rand(1, 30));
                    } else {
                        $date = Carbon::now();
                    }

                    // مطابقة يوم الحجز مع شفتات الخدمة الفعالة
                    while (!in_array(strtolower($date->format('l')), $activeDays)) {
                        $date->addDay();
                    }

                    // 🌟 الحسبة الذكية لاختيار شفت (صباحي أو مسائي) وتوليد كتل متوافقة 🌟
                    $shiftType = Arr::random(['morning', 'evening']);
                    
                    if ($shiftType === 'morning') {
                        // الشفت الصباحي يبدأ 09:00 وينتهي 17:00
                        $randomBlockIndex = rand(0, 3); 
                        $startBlockTime = Carbon::parse('09:00')->addHours($randomBlockIndex * $periodHours);
                        $endBlockTime   = $startBlockTime->copy()->addHours($periodHours);

                        if ($endBlockTime->format('H:i') > '17:00') {
                            $endBlockTime = Carbon::parse('17:00');
                            $startBlockTime = $endBlockTime->copy()->subHours($periodHours);
                        }
                    } else {
                        // الشفت المسائي يبدأ 18:00 وينتهي 23:00
                        $randomBlockIndex = rand(0, 2); 
                        $startBlockTime = Carbon::parse('18:00')->addHours($randomBlockIndex * $periodHours);
                        $endBlockTime   = $startBlockTime->copy()->addHours($periodHours);

                        if ($endBlockTime->format('H:i') > '23:00') {
                            $endBlockTime = Carbon::parse('23:00');
                            $startBlockTime = $endBlockTime->copy()->subHours($periodHours);
                        }
                    }

                    // تفادي كسر الحسابات لو كانت طول كتلة الخدمة أكبر من المساحة المتبقية بالشفت
                    if ($startBlockTime->format('H:i') >= $endBlockTime->format('H:i')) {
                        continue; 
                    }

                    $this->createBooking(
                        $customer, 
                        $randomService, 
                        $randomWorker, 
                        $address, 
                        $date->format('Y-m-d'), 
                        $startBlockTime->format('H:i'), 
                        $endBlockTime->format('H:i'), 
                        $randomStatus
                    );
                }
            }

            DB::commit();
            $this->command->info('functional and comprehensive bookings seeded successfully for all customers, covering a wide range of scenarios and ensuring robust testing of the booking system!');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('حدث خطأ في الـ Seeder: ' . $e->getMessage());
        }
    }

    /**
     * دالة مساعدة لإنشاء الحجز والربط بجدول Assignments والمحفظة
     */
    private function createBooking($customer, $service, $worker, $address, $date, $start, $end, $status)
    {
        $durationMinutes = Carbon::parse($start)->diffInMinutes(Carbon::parse($end));
        
        $booking = Booking::create([
            'booking_ref'      => 'HC-' . rand(100000, 999999),
            'customer_id'      => $customer->id,
            'service_id'       => $service->id,
            'address_id'       => $address->id,
            'worker_id'        => $worker->id,
            'booking_date'     => $date,
            'start_time'       => $start . ':00',
            'end_time'         => $end . ':00',
            'duration_minutes' => $durationMinutes,
            'price_subtotal'   => 150.00,
            'tax_amount'       => 15.00,
            'discount_amount'  => 0,
            'total_amount'     => 165.00,
            'status'           => $status,
            'payment_status'   => in_array($status, ['completed', 'Upcoming', 'inService']) ? 'paid' : 'unpaid',
        ]);

        // ربط العامل بالحجز
        BookingAssignment::create([
            'booking_id' => $booking->id,
            'worker_id'  => $worker->id,
            'status'     => 'active',
        ]);

        // إضافة حركة السجل التاريخي
        BookingHistory::create([
            'booking_id' => $booking->id,
            'new_status' => $status,
            'note'       => 'تم المحاكاة والتوليد الآلي لشفتات الكتل المتوازنة.',
            'created_by' => $customer->id,
        ]);
    }
}