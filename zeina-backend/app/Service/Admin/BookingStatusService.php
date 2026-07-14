<?php

namespace App\Service\Admin;

use App\Enums\Admin\BookingStatus;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\User;
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
        if (in_array($status, [BookingStatus::InService, BookingStatus::Completed], true)) {
            $today = \Carbon\Carbon::today()->toDateString();
            $bookingDate = \Carbon\Carbon::parse($booking->booking_date)->toDateString();
            if ($today !== $bookingDate) {
                throw new \Exception('Cannot change status to In Service or Completed unless today is the scheduled booking date. / لا يمكن تغيير الحالة إلى قيد التنفيذ أو مكتمل إلا في يوم الخدمة المجدول.', 400);
            }
        }

        if ($status === BookingStatus::Cancelled) {
            $bookingService = app(\App\Service\BookingService::class);
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
