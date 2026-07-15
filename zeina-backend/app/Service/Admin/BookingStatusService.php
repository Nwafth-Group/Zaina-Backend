<?php

namespace App\Service\Admin;

use App\Enums\Admin\BookingStatus;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\User;
use App\Service\BookingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applies admin-driven booking status transitions and records them in the
 * booking history. Single responsibility: the state change + its audit trail,
 * kept out of the controller.
 */
class BookingStatusService
{
    /**
     * Transition a booking to a new status and log the change atomically.
     * Returns the booking eager-loaded with the relations the listing resource
     * needs.
     */
    public function change(Booking $booking, BookingStatus $status, ?string $note, User $actor): Booking
    {
        $currentStatus = $booking->status;

        // Prevent moving backwards (sequential transition)
        if ($currentStatus === 'inService' && $status === BookingStatus::Upcoming) {
            throw new \Exception('Cannot change status from In Service back to Upcoming. / لا يمكن إرجاع حالة الحجز من قيد التنفيذ إلى قادمة.', 400);
        }
        if ($currentStatus === 'completed') {
            throw new \Exception('Cannot change status of a completed booking. / لا يمكن تغيير حالة حجز مكتمل بالفعل.', 400);
        }
        if ($currentStatus === 'cancelled') {
            throw new \Exception('Cannot change status of a cancelled booking. / لا يمكن تغيير حالة حجز ملغي بالفعل.', 400);
        }

        // Specific status validation rules
        if ($status === BookingStatus::InService) {
            $today = Carbon::today('Africa/Cairo')->toDateString();
            $bookingDate = Carbon::parse($booking->booking_date, 'Africa/Cairo')->toDateString();
            if ($today !== $bookingDate) {
                throw new \Exception('Cannot change status to In Service unless today is the scheduled booking date. / لا يمكن تغيير الحالة إلى قيد التنفيذ إلا في يوم الخدمة المجدول.', 400);
            }
        }

        if ($status === BookingStatus::Completed) {
            $start = Carbon::parse($booking->booking_date.' '.$booking->start_time, 'Africa/Cairo');
            $end = Carbon::parse($booking->booking_date.' '.$booking->end_time, 'Africa/Cairo');
            if ($end->lt($start)) {
                $end->addDay();
            }

            if (Carbon::now('Africa/Cairo')->lt($end)) {
                throw new \Exception('Cannot change status to Completed unless the booking end time has passed. / لا يمكن تغيير الحالة إلى مكتمل إلا بعد انتهاء وقت الخدمة المجدول.', 400);
            }
        }

        if ($status === BookingStatus::Cancelled) {
            $bookingService = app(BookingService::class);
            $bookingService->cancelBooking($booking->id, $actor->id, $note, true);

            return $booking->load(['customer', 'service', 'workers']);
        }

        return DB::transaction(function () use ($booking, $status, $note, $actor): Booking {
            $previousStatus = $booking->status;

            $booking->update(['status' => $status->value]);

            BookingHistory::create([
                'booking_id' => $booking->id,
                'old_status' => $previousStatus,
                'new_status' => $status->value,
                'note' => $note,
                'created_by' => $actor->id,
            ]);

            return $booking->load(['customer', 'service', 'workers']);
        });
    }
}
