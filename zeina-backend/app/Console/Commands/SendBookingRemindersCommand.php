<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Notifications\BookingNotification;
use Carbon\Carbon;
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
        // نبحث عن الحجوزات النشطة (Upcoming/Confirmed) التي ستبدأ بعد ساعة واحدة من الآن تماماً
        $oneHourTarget = $now->copy()->addHour();
        
        $oneHourBookings = Booking::whereIn('status', ['Upcoming'])
            ->where('booking_date', $oneHourTarget->toDateString())
            ->whereRaw("TIME_FORMAT(start_time, '%H:%i') = ?", [$oneHourTarget->format('H:i')])
            ->with('user')
            ->get();

        foreach ($oneHourBookings as $booking) {
            try {
                $user = $booking->user;
                if ($user) {
                    $startTimeFormatted = Carbon::parse($booking->start_time)->format('h:i A');
                    
                    $user->notify(new BookingNotification(
                        __('sms.reminder_1hour_title') ?? 'تذكير بموعد خدمتك ⏰',
                        __('sms.reminder_1hour_message', [
                            'date' => $booking->booking_date,
                            'time' => $startTimeFormatted
                        ]),
                        $booking->id,
                        [
                            'booking_ref'  => $booking->booking_ref,
                            'booking_date' => $booking->booking_date,
                            'booking_time' => $startTimeFormatted,
                            'reminder_type'=> '1_hour_before'
                        ]
                    ));
                }
            } catch (\Exception $e) {
                Log::error("Failed to send 1-hour reminder for Booking ID {$booking->id}: " . $e->getMessage());
            }
        }

        // ----------------------------------------------------
        // 🌟 ثانياً: لوجيك التذكير قبل بـ 15 دقيقة بالضبط
        // ----------------------------------------------------
        // نبحث عن الحجوزات التي ستبدأ بعد 15 دقيقة من الآن تماماً
        $fifteenMinsTarget = $now->copy()->addMinutes(15);

        $fifteenMinBookings = Booking::whereIn('status', ['Upcoming', 'Confirmed'])
            ->where('booking_date', $fifteenMinsTarget->toDateString())
            ->whereRaw("TIME_FORMAT(start_time, '%H:%i') = ?", [$fifteenMinsTarget->format('H:i')])
            ->with('user')
            ->get();

        foreach ($fifteenMinBookings as $booking) {
            try {
                $user = $booking->user;
                if ($user) {
                    $user->notify(new BookingNotification(
                        __('sms.reminder_15min_title') ?? 'مقدم الخدمة في الطريق 🚗',
                        __('sms.reminder_15min_message') ?? 'مقدم الخدمة في طريقه إليك وسيصل قريبًا.',
                        $booking->id,
                        [
                            'booking_ref'  => $booking->booking_ref,
                            'current_status'=> 'ProviderOnTheWay',
                            'reminder_type'=> '15_mins_before'
                        ]
                    ));
                }
            } catch (\Exception $e) {
                Log::error("Failed to send 15-minute reminder for Booking ID {$booking->id}: " . $e->getMessage());
            }
        }

        Log::info('Booking Reminders Command finished successfully.');
        return Command::SUCCESS;
    }
}