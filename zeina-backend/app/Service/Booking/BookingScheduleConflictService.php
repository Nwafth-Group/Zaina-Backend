<?php

namespace App\Service\Booking;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\Service;
use App\Models\Team;
use App\Models\Worker;
use Carbon\Carbon;

class BookingScheduleConflictService
{
    public const BUFFER_MINUTES = 60;

    /**
     * Check if a worker has any schedule conflict (direct overlap or 60-minute transition buffer)
     * for a given date and time window.
     *
     * @return string|null Localized error message if conflict exists, null if available.
     */
    public function checkWorkerConflict(
        Worker $worker,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null,
        bool $enforceBuffer = true
    ): ?string {
        if ($worker->status !== 'active') {
            return __('validation.booking_conflict.worker_inactive', ['name' => $worker->name]);
        }

        $newStart = Carbon::parse("{$date} {$startTime}");
        $newEnd = Carbon::parse("{$date} {$endTime}");

        // Direct overlap window: [newStart, newEnd]
        // Buffer-expanded window: [newStart - 60m, newEnd + 60m]
        $bufferStart = $enforceBuffer ? (clone $newStart)->subMinutes(self::BUFFER_MINUTES) : $newStart;
        $bufferEnd = $enforceBuffer ? (clone $newEnd)->addMinutes(self::BUFFER_MINUTES) : $newEnd;

        // Fetch all active assignments for this worker on this date
        $assignments = BookingAssignment::where('worker_id', $worker->id)
            ->where('status', 'active')
            ->whereHas('booking', function ($q) use ($date, $excludeBookingId) {
                $q->where('booking_date', $date)
                    ->whereIn('status', ['Upcoming', 'inService']);
                if ($excludeBookingId) {
                    $q->where('id', '!=', $excludeBookingId);
                }
            })
            ->with('booking')
            ->get();

        foreach ($assignments as $assignment) {
            $b = $assignment->booking;
            if (!$b) {
                continue;
            }

            $existingStart = Carbon::parse("{$b->booking_date} {$b->start_time}");
            $existingEnd = Carbon::parse("{$b->booking_date} {$b->end_time}");

            // 1. Direct overlap check: existingStart < newEnd && existingEnd > newStart
            if ($existingStart->lt($newEnd) && $existingEnd->gt($newStart)) {
                return __('validation.booking_conflict.direct_overlap', ['name' => $worker->name]);
            }

            // 2. 60-minute bidirectional transition gap check
            if ($enforceBuffer) {
                // If existing booking is before new booking: newStart - existingEnd must be >= 60 min
                if ($existingEnd->lte($newStart) && $existingEnd->gt($bufferStart)) {
                    return __('validation.booking_conflict.transition_gap_60min', ['name' => $worker->name]);
                }

                // If existing booking is after new booking: existingStart - newEnd must be >= 60 min
                if ($existingStart->gte($newEnd) && $existingStart->lt($bufferEnd)) {
                    return __('validation.booking_conflict.transition_gap_60min', ['name' => $worker->name]);
                }
            }
        }

        return null;
    }

    /**
     * Get busy worker IDs from a list of candidates for a specific slot.
     * Enforces direct overlap and 60-minute bidirectional transition buffer.
     *
     * @param int[] $workerIds
     * @return int[]
     */
    public function getBusyWorkerIdsForSlot(
        array $workerIds,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null,
        bool $enforceBuffer = true
    ): array {
        if (empty($workerIds)) {
            return [];
        }

        $newStart = Carbon::parse("{$date} {$startTime}");
        $newEnd = Carbon::parse("{$date} {$endTime}");

        $bufferStart = $enforceBuffer ? (clone $newStart)->subMinutes(self::BUFFER_MINUTES) : $newStart;
        $bufferEnd = $enforceBuffer ? (clone $newEnd)->addMinutes(self::BUFFER_MINUTES) : $newEnd;

        $query = BookingAssignment::whereIn('worker_id', $workerIds)
            ->where('status', 'active')
            ->whereHas('booking', function ($q) use ($date, $excludeBookingId, $newStart, $newEnd, $bufferStart, $bufferEnd, $enforceBuffer) {
                $q->where('booking_date', $date)
                    ->whereIn('status', ['Upcoming', 'inService'])
                    ->where(function ($timeQ) use ($newStart, $newEnd, $bufferStart, $bufferEnd, $enforceBuffer) {
                        // Direct overlap
                        $timeQ->where(function ($overlap) use ($newStart, $newEnd) {
                            $overlap->where('start_time', '<', $newEnd->format('H:i:s'))
                                    ->where('end_time', '>', $newStart->format('H:i:s'));
                        });

                        // 60-minute transition buffer
                        if ($enforceBuffer) {
                            $timeQ->orWhere(function ($bufferBefore) use ($newStart, $bufferStart) {
                                $bufferBefore->where('end_time', '<=', $newStart->format('H:i:s'))
                                             ->where('end_time', '>', $bufferStart->format('H:i:s'));
                            })->orWhere(function ($bufferAfter) use ($newEnd, $bufferEnd) {
                                $bufferAfter->where('start_time', '>=', $newEnd->format('H:i:s'))
                                            ->where('start_time', '<', $bufferEnd->format('H:i:s'));
                            });
                        }
                    });

                if ($excludeBookingId) {
                    $q->where('id', '!=', $excludeBookingId);
                }
            });

        return $query->pluck('worker_id')->unique()->values()->toArray();
    }

    /**
     * Check if a specific team is fully available for a slot.
     * All workers of the team must pass the individual conflict and 60-minute buffer checks.
     */
    public function isTeamAvailable(
        Team $team,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null
    ): bool {
        if ($team->status !== 'active') {
            return false;
        }

        $workers = $team->workers;
        if ($workers->isEmpty()) {
            return false;
        }

        $workerIds = $workers->pluck('id')->toArray();
        $busyIds = $this->getBusyWorkerIdsForSlot($workerIds, $date, $startTime, $endTime, $excludeBookingId, true);

        return empty($busyIds);
    }

    /**
     * Find an available active team for a service at a specific date and time.
     */
    public function getAvailableTeamForService(
        Service $service,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null
    ): ?Team {
        $teams = $service->teams()->where('status', 'active')->with('workers')->get();

        foreach ($teams as $team) {
            if ($this->isTeamAvailable($team, $date, $startTime, $endTime, $excludeBookingId)) {
                return $team;
            }
        }

        return null;
    }
}
