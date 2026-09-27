<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\WorkerAbsence;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckBookingDelaysAndRisksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:check-at-risk {--date= : Specific service date to evaluate, defaults to today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scans daily operations, checks transition gaps (60-min rule), and flags delayed consecutive bookings as At-Risk';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date') ?: now()->toDateString();
        $this->info("Scanning operations and transition risks for date: {$date}...");
        Log::info("Running CheckBookingDelaysAndRisksCommand for {$date}...");

        $now = now();
        $currentTimeStr = $now->format('H:i');
        $atRiskCount = 0;
        $clearedCount = 0;

        // 1. Group bookings by assigned worker
        $workerBookings = Booking::with(['service', 'team'])
            ->where('booking_date', $date)
            ->whereNotNull('worker_id')
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('start_time', 'asc')
            ->get()
            ->groupBy('worker_id');

        foreach ($workerBookings as $workerId => $bookings) {
            $sorted = $bookings->sortBy('start_time')->values();

            for ($i = 0; $i < $sorted->count() - 1; $i++) {
                $current = $sorted[$i];
                $next = $sorted[$i + 1];

                if (in_array($next->status, ['completed', 'cancelled'])) {
                    continue;
                }

                // Effective end of current service
                $effectiveEnd = $current->actual_end_time;
                if (!$effectiveEnd) {
                    if ($current->status === 'inService' && $currentTimeStr > $current->end_time) {
                        // Current is in progress and running late
                        $effectiveEnd = $currentTimeStr;
                    } else {
                        $effectiveEnd = $current->end_time;
                    }
                }

                if (!$effectiveEnd || !$next->start_time) {
                    continue;
                }

                $availableGap = Carbon::parse($date . ' ' . $effectiveEnd)->diffInMinutes(Carbon::parse($date . ' ' . $next->start_time), false);
                $mandatoryGap = 60;

                if ($availableGap < $mandatoryGap) {
                    $missingMins = $mandatoryGap - $availableGap;
                    $isOverlap = $availableGap < 0;

                    if ($isOverlap) {
                        $reasonAr = "تأخر انتهاء الحجز السابق #{$current->booking_ref} وتجاوز موعد بدء هذا الحجز ({$next->start_time}). الفاصل المطلوب 60 دقيقة غير متوفر بالكامل.";
                        $reasonEn = "Previous booking #{$current->booking_ref} ended late and overlapped with this booking ({$next->start_time}). Mandatory 60-min transition gap is missing.";
                    } else {
                        $reasonAr = "تأخر انتهاء الحجز السابق #{$current->booking_ref} حتى الساعة {$effectiveEnd}. الفاصل المتبقي للانتقال هو {$availableGap} دقيقة فقط (أقل من الـ 60 دقيقة الإلزامية بنقص {$missingMins} دقيقة).";
                        $reasonEn = "Previous booking #{$current->booking_ref} completed late. Available transition gap is only {$availableGap} min, which is less than the mandatory 60 min (deficit of {$missingMins} min).";
                    }

                    if (!$next->is_at_risk || !str_contains($next->at_risk_reason ?? '', $current->booking_ref)) {
                        $next->update([
                            'is_at_risk' => true,
                            'at_risk_reason' => $reasonAr . "\n" . $reasonEn,
                        ]);

                        BookingHistory::create([
                            'booking_id' => $next->id,
                            'old_status' => $next->status,
                            'new_status' => $next->status,
                            'note' => "Flagged At-Risk by scheduled scan: transition gap deficit from #{$current->booking_ref}",
                            'created_by' => 1,
                        ]);

                        $atRiskCount++;
                    }
                }
            }
        }

        // 2. Group bookings by assigned team
        $teamBookings = Booking::with(['service', 'team'])
            ->where('booking_date', $date)
            ->whereNotNull('team_id')
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('start_time', 'asc')
            ->get()
            ->groupBy('team_id');

        foreach ($teamBookings as $teamId => $bookings) {
            $sorted = $bookings->sortBy('start_time')->values();

            for ($i = 0; $i < $sorted->count() - 1; $i++) {
                $current = $sorted[$i];
                $next = $sorted[$i + 1];

                if (in_array($next->status, ['completed', 'cancelled'])) {
                    continue;
                }

                $effectiveEnd = $current->actual_end_time;
                if (!$effectiveEnd) {
                    if ($current->status === 'inService' && $currentTimeStr > $current->end_time) {
                        $effectiveEnd = $currentTimeStr;
                    } else {
                        $effectiveEnd = $current->end_time;
                    }
                }

                if (!$effectiveEnd || !$next->start_time) {
                    continue;
                }

                $availableGap = Carbon::parse($date . ' ' . $effectiveEnd)->diffInMinutes(Carbon::parse($date . ' ' . $next->start_time), false);
                $mandatoryGap = 60;

                if ($availableGap < $mandatoryGap) {
                    $missingMins = $mandatoryGap - $availableGap;
                    $teamName = $current->team?->name ?? "Team #{$teamId}";

                    $reasonAr = "تأخر انتهاء حجز فريق ({$teamName}) السابق #{$current->booking_ref} حتى الساعة {$effectiveEnd}. الفاصل المتبقي للانتقال هو {$availableGap} دقيقة فقط (نقص بمقدار {$missingMins} دقيقة عن الـ 60 دقيقة الإلزامية).";
                    $reasonEn = "Team ({$teamName}) previous booking #{$current->booking_ref} ended late. Available transition gap is only {$availableGap} min (deficit of {$missingMins} min from mandatory 60 min).";

                    if (!$next->is_at_risk || !str_contains($next->at_risk_reason ?? '', $current->booking_ref)) {
                        $next->update([
                            'is_at_risk' => true,
                            'at_risk_reason' => $reasonAr . "\n" . $reasonEn,
                        ]);

                        BookingHistory::create([
                            'booking_id' => $next->id,
                            'old_status' => $next->status,
                            'new_status' => $next->status,
                            'note' => "Team booking flagged At-Risk: transition gap deficit from #{$current->booking_ref}",
                            'created_by' => 1,
                        ]);

                        $atRiskCount++;
                    }
                }
            }
        }

        // 3. Scan Worker Absences overlapping today's active bookings
        $activeAbsences = WorkerAbsence::where('status', 'reported')
            ->whereDate('absence_start', '<=', $date)
            ->whereDate('absence_end', '>=', $date)
            ->with('worker')
            ->get();

        foreach ($activeAbsences as $absence) {
            $absentWorkerId = $absence->worker_id;
            $workerName = $absence->worker?->name ?? "Worker #{$absentWorkerId}";

            $affected = Booking::where('booking_date', $date)
                ->where(function ($q) use ($absentWorkerId) {
                    $q->where('worker_id', $absentWorkerId)
                      ->orWhereHas('workers', fn($wq) => $wq->where('workers.id', $absentWorkerId));
                })
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->get();

            foreach ($affected as $ab) {
                if (!$ab->is_at_risk) {
                    $ab->update([
                        'is_at_risk' => true,
                        'at_risk_reason' => "العامل المكلف ({$workerName}) مسجل غياب للفترة المحددة. السبب: {$absence->reason}\nAssigned worker ({$workerName}) reported absent. Reason: {$absence->reason}",
                    ]);
                    $atRiskCount++;
                }
            }
        }

        $this->info("Completed scan for {$date}: {$atRiskCount} bookings flagged At-Risk.");
        Log::info("CheckBookingDelaysAndRisksCommand finished for {$date}: {$atRiskCount} flagged At-Risk.");

        return Command::SUCCESS;
    }
}
