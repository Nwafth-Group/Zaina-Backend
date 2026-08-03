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

        // Prevent cancelling or moving backwards if inService, completed, or cancelled
        if ($currentStatus === 'inService' && ($status === BookingStatus::Upcoming || $status === BookingStatus::Cancelled)) {
            throw new \Exception('Cannot cancel or revert a booking that is currently in service. / لا يمكن إلغاء أو تعديل حالة حجز قيد التنفيذ.', 400);
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
            $start = Carbon::parse($booking->booking_date . ' ' . $booking->start_time, 'Africa/Cairo');
            $end = Carbon::parse($booking->booking_date . ' ' . $booking->end_time, 'Africa/Cairo');
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

            if ($status === BookingStatus::Completed) {
                \App\Service\LoyaltyPointService::awardPointsForCompletedBooking($booking);
            }

            // Notify customer of status change (In Service or Completed)
            try {
                if ($booking->customer) {
                    $lang = $booking->customer->preferred_language === 'en' ? 'en' : 'ar';
                    if ($status === BookingStatus::InService) {
                        $title = $lang === 'en' ? 'Service Started' : 'بدأت الخدمة';
                        $body = $lang === 'en'
                            ? "Your service for booking ref: {$booking->booking_ref} is now in service."
                            : "حجزك رقم {$booking->booking_ref} هو الآن قيد التنفيذ.";
                        $booking->customer->notify(new \App\Notifications\BookingNotification(
                            $title,
                            $body,
                            $booking->id,
                            [
                                'booking_ref' => $booking->booking_ref,
                                'current_status' => 'inService',
                            ]
                        ));
                    } elseif ($status === BookingStatus::Completed) {
                        $title = $lang === 'en' ? 'Service Completed' : 'اكتملت الخدمة';
                        $body = $lang === 'en'
                            ? "Your service for booking ref: {$booking->booking_ref} has been completed. Thank you!"
                            : "تم إتمام خدمتك للحجز رقم {$booking->booking_ref} بنجاح. شكراً لك!";
                        $booking->customer->notify(new \App\Notifications\BookingNotification(
                            $title,
                            $body,
                            $booking->id,
                            [
                                'booking_ref' => $booking->booking_ref,
                                'current_status' => 'completed',
                            ]
                        ));

                        // Notify Admins of completed booking
                        $admins = User::whereIn('type', ['super_admin', 'operation'])->get();
                        foreach ($admins as $admin) {
                            $admin->notify(new \App\Notifications\AdminBookingEventNotification('completed', $booking));
                        }
                    }
                }
            } catch (\Exception $ex) {
                \Illuminate\Support\Facades\Log::error('Status transition notification failure: ' . $ex->getMessage());
            }

            return $booking->load(['customer', 'service', 'workers']);
        });
    }
}
