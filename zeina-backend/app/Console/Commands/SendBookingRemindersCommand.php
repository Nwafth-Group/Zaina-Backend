<?php

namespace App\Console\Commands;

use App\Models\BookingVisit;
use App\Notifications\BookingNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendBookingRemindersCommand extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'إرسال تذكيرات تلقائية للعملاء قبل ساعة وقبل 15 دقيقة من موعد الحجز';

    public function handle()
    {
        Log::info('Start running Booking Reminders Command...');

        $now = Carbon::now()->startOfMinute();

        // ----------------------------------------------------
        //  أولاً: لوجيك التذكير قبل بـ ساعة (بين 45 و 75 دقيقة من الآن)
        // ----------------------------------------------------
        $oneHourMin = $now->copy()->addMinutes(45);
        $oneHourMax = $now->copy()->addMinutes(75);

        $oneHourQuery = BookingVisit::where('status', 'Upcoming');
        $this->applyDateTimeRange($oneHourQuery, $oneHourMin, $oneHourMax);
        $oneHourVisits = $oneHourQuery->with('booking.customer')->get();

        foreach ($oneHourVisits as $visit) {
            try {
                $booking = $visit->booking;
                $user = $booking?->customer;
                if ($user) {
                    $cacheKey = "booking_visit_reminder_{$visit->id}_1hour";
                    if (Cache::has($cacheKey)) {
                        continue;
                    }

                    $startTimeFormatted = Carbon::parse($visit->start_time)->format('h:i A');

                    $user->notify(new BookingNotification(
                        __('sms.reminder_1hour_title') ?? 'تذكير بموعد خدمتك ⏰',
                        __('sms.reminder_1hour_message', [
                            'ref' => $booking->booking_ref,
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

                    Cache::put($cacheKey, true, now()->addHours(12));
                }
            } catch (\Exception $e) {
                Log::error("Failed to send 1-hour reminder for Booking Visit ID {$visit->id}: ".$e->getMessage());
            }
        }

        // ----------------------------------------------------
        // 🌟 ثانياً: لوجيك التذكير قبل بـ 15 دقيقة (بين 0 و 30 دقيقة من الآن)
        // ----------------------------------------------------
        $fifteenMinMin = $now->copy();
        $fifteenMinMax = $now->copy()->addMinutes(30);

        $fifteenMinQuery = BookingVisit::where('status', 'Upcoming');
        $this->applyDateTimeRange($fifteenMinQuery, $fifteenMinMin, $fifteenMinMax);
        $fifteenMinVisits = $fifteenMinQuery->with('booking.customer')->get();

        foreach ($fifteenMinVisits as $visit) {
            try {
                $booking = $visit->booking;
                $user = $booking?->customer;
                if ($user) {
                    $cacheKey = "booking_visit_reminder_{$visit->id}_15mins";
                    if (Cache::has($cacheKey)) {
                        continue;
                    }

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

                    Cache::put($cacheKey, true, now()->addHours(12));
                }
            } catch (\Exception $e) {
                Log::error("Failed to send 15-minute reminder for Booking Visit ID {$visit->id}: ".$e->getMessage());
            }
        }

        Log::info('Booking Reminders Command finished successfully.');

        return Command::SUCCESS;
    }

    /**
     * Helper to apply database-agnostic datetime range filtering for visit_date and start_time.
     */
    private function applyDateTimeRange($query, Carbon $min, Carbon $max)
    {
        $minDate = $min->toDateString();
        $maxDate = $max->toDateString();
        $minTime = $min->toTimeString();
        $maxTime = $max->toTimeString();

        if ($minDate === $maxDate) {
            $query->where('visit_date', $minDate)
                ->whereBetween('start_time', [$minTime, $maxTime]);
        } else {
            $query->where(function ($q) use ($minDate, $maxDate, $minTime, $maxTime) {
                $q->where(function ($sub) use ($minDate, $minTime) {
                    $sub->where('visit_date', $minDate)
                        ->where('start_time', '>=', $minTime);
                })->orWhere(function ($sub) use ($maxDate, $maxTime) {
                    $sub->where('visit_date', $maxDate)
                        ->where('start_time', '<=', $maxTime);
                });
            });
        }
    }
}
