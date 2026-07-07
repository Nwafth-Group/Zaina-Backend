<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingVisit;
use App\Notifications\BookingNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendBookingRemindersCommand extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'إرسال تذكيرات تلقائية للعملاء قبل ساعة وقبل 15 دقيقة من موعد الحجز';

    public function handle()
    {
        Log::info('Start running Booking Reminders Command...');

        // وقت المرجعية الحالي (مهم جداً تقريب الوقت للدقيقة لإصابة الهدف بالتمام)
        $now = Carbon::now()->startOfMinute();

        // ----------------------------------------------------
        //  أولاً: لوجيك التذكير قبل بـ ساعة واحدة بالضبط
        // ----------------------------------------------------
        // نبحث عن زيارات الحجوزات النشطة (Upcoming) التي ستبدأ بعد ساعة واحدة من الآن تماماً
        $oneHourTarget = $now->copy()->addHour();

        $oneHourVisits = BookingVisit::where('status', 'Upcoming')
            ->where('visit_date', $oneHourTarget->toDateString())
            ->whereRaw("TIME_FORMAT(start_time, '%H:%i') = ?", [$oneHourTarget->format('H:i')])
            ->with('booking.customer')
            ->get();

        foreach ($oneHourVisits as $visit) {
            try {
                $booking = $visit->booking;
                $user = $booking?->customer;
                if ($user) {
                    $startTimeFormatted = Carbon::parse($visit->start_time)->format('h:i A');

                    $user->notify(new BookingNotification(
                        __('sms.reminder_1hour_title') ?? 'تذكير بموعد خدمتك ⏰',
                        __('sms.reminder_1hour_message', [
                            'date' => $visit->visit_date,
                            'time' => $startTimeFormatted,
                        ]),
                        $booking->id,
                        [
                            'booking_ref' => $booking->booking_ref,
                            'booking_date' => $visit->visit_date,
                            'booking_time' => $startTimeFormatted,
                            'reminder_type' => '1_hour_before',
                        ]
                    ));
                }
            } catch (\Exception $e) {
                Log::error("Failed to send 1-hour reminder for Booking Visit ID {$visit->id}: ".$e->getMessage());
            }
        }

        // ----------------------------------------------------
        // 🌟 ثانياً: لوجيك التذكير قبل بـ 15 دقيقة بالضبط
        // ----------------------------------------------------
        // نبحث عن زيارات الحجوزات التي ستبدأ بعد 15 دقيقة من الآن تماماً
        $fifteenMinsTarget = $now->copy()->addMinutes(15);

        $fifteenMinVisits = BookingVisit::where('status', 'Upcoming')
            ->where('visit_date', $fifteenMinsTarget->toDateString())
            ->whereRaw("TIME_FORMAT(start_time, '%H:%i') = ?", [$fifteenMinsTarget->format('H:i')])
            ->with('booking.customer')
            ->get();

        foreach ($fifteenMinVisits as $visit) {
            try {
                $booking = $visit->booking;
                $user = $booking?->customer;
                if ($user) {
                    $user->notify(new BookingNotification(
                        __('sms.reminder_15min_title') ?? 'مقدم الخدمة في الطريق 🚗',
                        __('sms.reminder_15min_message') ?? 'مقدم الخدمة في طريقه إليك وسيصل قريبًا.',
                        $booking->id,
                        [
                            'booking_ref' => $booking->booking_ref,
                            'current_status' => 'ProviderOnTheWay',
                            'reminder_type' => '15_mins_before',
                        ]
                    ));
                }
            } catch (\Exception $e) {
                Log::error("Failed to send 15-minute reminder for Booking Visit ID {$visit->id}: ".$e->getMessage());
            }
        }

        Log::info('Booking Reminders Command finished successfully.');

        return Command::SUCCESS;
    }
}
