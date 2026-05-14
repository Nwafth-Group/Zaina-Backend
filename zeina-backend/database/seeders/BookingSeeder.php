<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Service;
use App\Models\Worker;
use App\Models\CustomerAddress;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\BookingAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. جلب جميع العملاء وجميع الخدمات (مع عمالها وجداولها)
        $customers = User::where('type', 'customer')->get();
        $services = Service::with(['workers', 'schedules'])->get();

        if ($customers->isEmpty() || $services->isEmpty()) {
            $this->command->error('يجب أن يكون لديك عملاء وخدمات مسجلة لتشغيل الـ Seeder.');
            return;
        }

        DB::beginTransaction();
        try {
            // =========================================================
            // الجزء الأول: بناء سيناريوهات التضارب لكل خدمة (Test Scenario)
            // =========================================================
            $testCustomer = $customers->first(); // سنستخدم العميل الأول كحقل تجارب
            $testAddress = CustomerAddress::where('user_id', $testCustomer->id)->first();

            foreach ($services as $service) {
                // البحث عن الأيام الفعالة لهذه الخدمة تحديداً
                $activeDays = $service->schedules->where('is_active', true)->pluck('day')->map(function($day) {
                    return strtolower($day);
                })->toArray();

                // إذا الخدمة ليس لها أي شفت فعال، نتخطاها
                if (empty($activeDays)) continue;

                // 🌟 الذكاء هنا: إيجاد أقرب يوم عمل قادم للخدمة 🌟
                $testDate = Carbon::tomorrow();
                while (!in_array(strtolower($testDate->format('l')), $activeDays)) {
                    $testDate->addDay(); // نزيد يوم حتى نصل ليوم فيه شفت فعال
                }
                $validTestDateStr = $testDate->format('Y-m-d');

                // جلب 3 عمال لهذه الخدمة (إذا توفر)
                $testWorkers = $service->workers->take(3);
                
                if ($testWorkers->count() >= 2 && $testAddress) {
                    // العامل الأول: نغلق عليه جزء كبير من الشفت (سيناريو التضارب القوي)
                    $this->createBooking($testCustomer, $service, $testWorkers[0], $testAddress, $validTestDateStr, '09:00', '11:00', 'Upcoming');
                    $this->createBooking($testCustomer, $service, $testWorkers[0], $testAddress, $validTestDateStr, '12:00', '15:00', 'Upcoming');

                    // العامل الثاني: نغلقه جزئياً في المنتصف
                    $this->createBooking($testCustomer, $service, $testWorkers[1], $testAddress, $validTestDateStr, '10:00', '12:00', 'Upcoming');

                    $this->command->info("تم تجهيز التضاربات لخدمة ({$service->id}) في يوم {$validTestDateStr} (" . $testDate->format('l') . ")");
                }
            }

            // =========================================================
            // الجزء الثاني: حجوزات عشوائية لـ "جميع" العملاء
            // =========================================================
            $statuses = ['completed', 'cancelled', 'inService', 'Upcoming'];

            foreach ($customers as $customer) {
                // التأكد أن العميل لديه عنوان، وإلا ننشئ له واحد وهمي لضمان نجاح الحجز
                $address = CustomerAddress::where('user_id', $customer->id)->first();
                if (!$address) {
                    $address = CustomerAddress::create([
                        'user_id' => $customer->id,
                        'area_id' => 1, // افترض أن هناك منطقة رقم 1
                        'street'  => 'شارع تجريبي',
                        'type'    => 'apartment'
                    ]);
                }

                // ننشئ 4 حجوزات لكل عميل (بخدمات مختلفة إن أمكن)
                for ($i = 0; $i < 4; $i++) {
                    $randomService = $services->random();
                    $activeDays = $randomService->schedules->where('is_active', true)->pluck('day')->map(fn($d) => strtolower($d))->toArray();
                    
                    if (empty($activeDays) || $randomService->workers->isEmpty()) continue;

                    $randomWorker = $randomService->workers->random();
                    $randomStatus = $statuses[array_rand($statuses)];
                    
                    // تحديد تاريخ عشوائي، مع التأكد أنه يقع في يوم عمل فعال للخدمة
                    if ($randomStatus === 'Upcoming') {
                        $date = Carbon::now()->addDays(rand(2, 14));
                    } elseif (in_array($randomStatus, ['completed', 'cancelled'])) {
                        $date = Carbon::now()->subDays(rand(1, 30));
                    } else { // inService
                        $date = Carbon::now();
                    }

                    // لف العجلة حتى نجد يوماً يطابق أيام الشفتات الفعالة للخدمة العشوائية
                    while (!in_array(strtolower($date->format('l')), $activeDays)) {
                        $date->addDay();
                    }

                    $startHour = rand(9, 14); // أوقات بين 9 صباحاً و 2 ظهراً
                    $duration = rand(2, 4); // مدة 2 إلى 4 ساعات
                    $startStr = str_pad($startHour, 2, '0', STR_PAD_LEFT) . ':00';
                    $endStr = str_pad($startHour + $duration, 2, '0', STR_PAD_LEFT) . ':00';

                    $this->createBooking($customer, $randomService, $randomWorker, $address, $date->format('Y-m-d'), $startStr, $endStr, $randomStatus);
                }
            }

            DB::commit();
            $this->command->info('تم إنشاء حجوزات لجميع العملاء وتجهيز سيناريوهات الفحص بنجاح!');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * دالة مساعدة لإنشاء الحجز وتوابعه
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

        BookingAssignment::create([
            'booking_id' => $booking->id,
            'worker_id'  => $worker->id,
            'status'     => 'active',
        ]);

        BookingHistory::create([
            'booking_id' => $booking->id,
            'new_status' => $status,
            'note'       => 'تم الإنشاء عبر الـ Seeder',
            'created_by' => $customer->id,
        ]);
    }
}