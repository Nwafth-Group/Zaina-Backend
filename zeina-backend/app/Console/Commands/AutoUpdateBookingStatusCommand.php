<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\BookingVisit;
use App\Models\BookingAssignment;
use App\Notifications\BookingNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoUpdateBookingStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:auto-update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically transitions upcoming bookings to inService and inService to completed based on schedule';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Start running Auto Update Booking Status Command...');
        Log::info('Start running Auto Update Booking Status Command...');
        $now = Carbon::now();

        // 1. Transition Upcoming Visits -> inService
        $upcomingVisits = BookingVisit::where('status', 'Upcoming')
            ->where(function ($query) use ($now) {
                $query->where('visit_date', '<', $now->toDateString())
                    ->orWhere(function ($q) use ($now) {
                        $q->where('visit_date', $now->toDateString())
                            ->where('start_time', '<=', $now->format('H:i:s'));
                    });
            })
            ->with('booking.customer')
            ->get();

        foreach ($upcomingVisits as $visit) {
            try {
                $booking = $visit->booking;
                if (!$booking) {
                    continue;
                }

                DB::transaction(function () use ($visit, $booking) {
                    $visit->update(['status' => 'inService']);

                    if ($booking->status === 'Upcoming') {
                        $booking->update(['status' => 'inService']);
                        BookingHistory::create([
                            'booking_id' => $booking->id,
                            'old_status' => 'Upcoming',
                            'new_status' => 'inService',
                            'note'       => 'Automated status change: booking service started (first visit active).',
                            'created_by' => null,
                        ]);
                    }
                });

                if ($booking->customer && $booking->wasChanged('status')) {
                    $booking->customer->notify(new BookingNotification(
                        'بدأت خدمتك الآن 🏠',
                        'تم بدء الخدمة الخاصة بحجزك برقم ' . $booking->booking_ref,
                        $booking->id,
                        [
                            'booking_ref' => $booking->booking_ref,
                            'current_status' => 'inService',
                        ]
                    ));
                }
                $this->info("Booking Visit ID {$visit->id} (Booking ID {$booking->id}) transitioned to inService.");
                Log::info("Booking Visit ID {$visit->id} (Booking ID {$booking->id}) transitioned to inService.");
            } catch (\Exception $e) {
                $this->error("Failed to transition Booking Visit ID {$visit->id} to inService: " . $e->getMessage());
                Log::error("Failed to transition Booking Visit ID {$visit->id} to inService: " . $e->getMessage());
            }
        }

        // 1b. Transition Upcoming Bookings (without visits) -> inService
        $upcomingDirectBookings = Booking::where('status', 'Upcoming')
            ->whereDoesntHave('visits')
            ->where(function ($query) use ($now) {
                $query->where('booking_date', '<', $now->toDateString())
                    ->orWhere(function ($q) use ($now) {
                        $q->where('booking_date', $now->toDateString())
                            ->where('start_time', '<=', $now->format('H:i:s'));
                    });
            })
            ->with('customer')
            ->get();

        foreach ($upcomingDirectBookings as $booking) {
            try {
                DB::transaction(function () use ($booking) {
                    $booking->update(['status' => 'inService']);
                    BookingHistory::create([
                        'booking_id' => $booking->id,
                        'old_status' => 'Upcoming',
                        'new_status' => 'inService',
                        'note'       => 'Automated status change: booking service started (direct transition).',
                        'created_by' => null,
                    ]);
                });

                if ($booking->customer) {
                    $booking->customer->notify(new BookingNotification(
                        'بدأت خدمتك الآن 🏠',
                        'تم بدء الخدمة الخاصة بحجزك برقم ' . $booking->booking_ref,
                        $booking->id,
                        [
                            'booking_ref' => $booking->booking_ref,
                            'current_status' => 'inService',
                        ]
                    ));
                }
                $this->info("Booking ID {$booking->id} transitioned directly to inService.");
                Log::info("Booking ID {$booking->id} transitioned directly to inService.");
            } catch (\Exception $e) {
                $this->error("Failed to transition Booking ID {$booking->id} directly to inService: " . $e->getMessage());
                Log::error("Failed to transition Booking ID {$booking->id} directly to inService: " . $e->getMessage());
            }
        }

        // 2. Transition inService Visits -> completed
        $inServiceVisits = BookingVisit::where('status', 'inService')
            ->where(function ($query) use ($now) {
                $query->where('visit_date', '<', $now->toDateString())
                    ->orWhere(function ($q) use ($now) {
                        $q->where('visit_date', $now->toDateString())
                            ->where('end_time', '<=', $now->format('H:i:s'));
                    });
            })
            ->with('booking.customer')
            ->get();

        foreach ($inServiceVisits as $visit) {
            try {
                $booking = $visit->booking;
                if (!$booking) {
                    continue;
                }

                DB::transaction(function () use ($visit, $booking) {
                    $visit->update(['status' => 'completed']);

                    // Check if ALL visits under this parent booking are now completed
                    $allVisitsCompleted = !BookingVisit::where('booking_id', $booking->id)
                        ->where('status', '!=', 'completed')
                        ->exists();

                    if ($allVisitsCompleted && $booking->status !== 'completed') {
                        $booking->update(['status' => 'completed']);
                        BookingHistory::create([
                            'booking_id' => $booking->id,
                            'old_status' => 'inService',
                            'new_status' => 'completed',
                            'note'       => 'Automated status change: all scheduled visits completed.',
                            'created_by' => null,
                        ]);
                        \App\Service\LoyaltyPointService::awardPointsForCompletedBooking($booking);
                    }
                });

                if ($booking->customer && $booking->wasChanged('status')) {
                    $booking->customer->notify(new BookingNotification(
                        'اكتملت خدمتك بنجاح ✅',
                        'تم إتمام الخدمة لحجزك رقم ' . $booking->booking_ref . ' بنجاح. شكراً لك!',
                        $booking->id,
                        [
                            'booking_ref' => $booking->booking_ref,
                            'current_status' => 'completed',
                        ]
                    ));
                }
                $this->info("Booking Visit ID {$visit->id} (Booking ID {$booking->id}) transitioned to completed.");
                Log::info("Booking Visit ID {$visit->id} (Booking ID {$booking->id}) transitioned to completed.");
            } catch (\Exception $e) {
                $this->error("Failed to transition Booking Visit ID {$visit->id} to completed: " . $e->getMessage());
                Log::error("Failed to transition Booking Visit ID {$visit->id} to completed: " . $e->getMessage());
            }
        }

        // 2b. Transition inService Bookings (without visits) -> completed
        $inServiceDirectBookings = Booking::where('status', 'inService')
            ->whereDoesntHave('visits')
            ->where(function ($query) use ($now) {
                $query->where('booking_date', '<', $now->toDateString())
                    ->orWhere(function ($q) use ($now) {
                        $q->where('booking_date', $now->toDateString())
                            ->where('end_time', '<=', $now->format('H:i:s'));
                    });
            })
            ->with('customer')
            ->get();

        foreach ($inServiceDirectBookings as $booking) {
            try {
                DB::transaction(function () use ($booking) {
                    $booking->update(['status' => 'completed']);
                    BookingHistory::create([
                        'booking_id' => $booking->id,
                        'old_status' => 'inService',
                        'new_status' => 'completed',
                        'note'       => 'Automated status change: scheduled service completed (direct transition).',
                        'created_by' => null,
                    ]);
                    \App\Service\LoyaltyPointService::awardPointsForCompletedBooking($booking);
                });

                if ($booking->customer) {
                    $booking->customer->notify(new BookingNotification(
                        'اكتملت خدمتك بنجاح ✅',
                        'تم إتمام الخدمة لحجزك رقم ' . $booking->booking_ref . ' بنجاح. شكراً لك!',
                        $booking->id,
                        [
                            'booking_ref' => $booking->booking_ref,
                            'current_status' => 'completed',
                        ]
                    ));
                }
                $this->info("Booking ID {$booking->id} transitioned directly to completed.");
                Log::info("Booking ID {$booking->id} transitioned directly to completed.");
            } catch (\Exception $e) {
                $this->error("Failed to transition Booking ID {$booking->id} directly to completed: " . $e->getMessage());
                Log::error("Failed to transition Booking ID {$booking->id} directly to completed: " . $e->getMessage());
            }
        }

        $this->info('Auto Update Booking Status Command finished successfully.');
        Log::info('Auto Update Booking Status Command finished successfully.');
        return Command::SUCCESS;
    }
}
