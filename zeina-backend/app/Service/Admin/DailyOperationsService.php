<?php

namespace App\Service\Admin;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingHistory;
use App\Models\Team;
use App\Models\Worker;
use App\Models\WorkerAbsence;
use App\Models\User;
use App\Models\TransportGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyOperationsService
{
    /**
     * US-A30: Daily Operations Dashboard Summary & Master Table.
     */
    public function getDailyDashboard(string $date, array $filters = []): array
    {
        $query = Booking::with([
            'customer',
            'service.category',
            'worker',
            'workers',
            'team.workers',
            'address.serviceZone',
            'starter',
            'completer',
            'visits.starter',
            'visits.completer',
        ])->forDate($date);

        if (!empty($filters['status'])) {
            $status = $filters['status'];
            $query->where(function ($sq) use ($date, $status) {
                $sq->whereHas('visits', function ($vq) use ($date, $status) {
                    $vq->whereDate('visit_date', $date)->where('status', $status);
                })->orWhere(function ($sub) use ($status) {
                    $sub->doesntHave('visits')->where('status', $status);
                });
            });
        }

        if (isset($filters['is_at_risk']) && $filters['is_at_risk'] !== '') {
            $query->where('is_at_risk', filter_var($filters['is_at_risk'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (!empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('booking_ref', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('first_name', 'LIKE', "%{$search}%")
                         ->orWhere('last_name', 'LIKE', "%{$search}%")
                         ->orWhere('phone_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        $allDayBookings = Booking::with('visits')->forDate($date)->get();

        $effectiveStatus = function ($b) use ($date) {
            if ($b->visits && $b->visits->isNotEmpty()) {
                $matchingVisit = $b->visits->first(fn($v) => Carbon::parse($v->visit_date)->toDateString() === $date);
                if ($matchingVisit) {
                    return $matchingVisit->status;
                }
            }
            return $b->status;
        };

        // Calculate KPI Metrics based on the specific visit status for this date
        $nonCancelledBookings = $allDayBookings->filter(fn($b) => !in_array($effectiveStatus($b), ['cancelled', 'Cancelled']));

        $metrics = [
            'total_bookings' => $allDayBookings->count(),
            'upcoming' => $allDayBookings->filter(fn($b) => $effectiveStatus($b) === 'Upcoming')->count(),
            'in_service' => $allDayBookings->filter(fn($b) => $effectiveStatus($b) === 'inService')->count(),
            'completed' => $allDayBookings->filter(fn($b) => $effectiveStatus($b) === 'completed')->count(),
            'cancelled' => $allDayBookings->filter(fn($b) => in_array($effectiveStatus($b), ['cancelled', 'Cancelled']))->count(),
            'at_risk' => $nonCancelledBookings->where('is_at_risk', true)->count(),
            'unassigned' => $nonCancelledBookings->filter(function ($b) use ($effectiveStatus) {
                return in_array($effectiveStatus($b), ['Upcoming']) && empty($b->worker_id) && empty($b->team_id);
            })->count(),
            'active_teams' => $nonCancelledBookings->whereNotNull('team_id')->pluck('team_id')->unique()->count(),
            'active_workers' => $nonCancelledBookings->whereNotNull('worker_id')->pluck('worker_id')->unique()->count(),
        ];

        // Fetch Transport Groups and Group Workers for this date
        $transportGroups = TransportGroup::with(['driver', 'departurePoint', 'bookings', 'groupWorkers'])
            ->whereDate('service_date', $date)
            ->get();

        $groupLookup = [];
        foreach ($transportGroups as $tg) {
            // Only assign transport if an active driver is assigned to the group
            if (!$tg->driver_id || !$tg->driver || $tg->driver->status !== 'active') {
                continue;
            }

            foreach ($tg->bookings as $b) {
                $groupLookup[$b->id] = [
                    'group_id' => $tg->id,
                    'start_time' => $tg->start_time,
                    'departure_time' => $tg->departure_time,
                    'departure_point' => $tg->departurePoint?->name,
                    'driver_id' => $tg->driver->id,
                    'driver_name' => $tg->driver->name,
                    'driver_phone' => $tg->driver->mobile ?? $tg->driver->phone,
                    'vehicle_type' => $tg->vehicle_type ?? $tg->driver->vehicle_type,
                    'vehicle_plate' => $tg->vehicle_plate ?? $tg->driver->vehicle_plate,
                    'vehicle_capacity' => $tg->vehicle_capacity ?? $tg->driver->vehicle_capacity ?? 5,
                ];
            }
            foreach ($tg->groupWorkers as $gw) {
                if ($gw->booking_id && !isset($groupLookup[$gw->booking_id])) {
                    $groupLookup[$gw->booking_id] = [
                        'group_id' => $tg->id,
                        'start_time' => $tg->start_time,
                        'departure_time' => $tg->departure_time,
                        'departure_point' => $tg->departurePoint?->name,
                        'driver_id' => $tg->driver->id,
                        'driver_name' => $tg->driver->name,
                        'driver_phone' => $tg->driver->mobile ?? $tg->driver->phone,
                        'vehicle_type' => $tg->vehicle_type ?? $tg->driver->vehicle_type,
                        'vehicle_plate' => $tg->vehicle_plate ?? $tg->driver->vehicle_plate,
                        'vehicle_capacity' => $tg->vehicle_capacity ?? $tg->driver->vehicle_capacity ?? 5,
                    ];
                }
            }
        }

        $bookings = $query->orderBy('start_time', 'asc')->get()->map(function ($b) use ($groupLookup, $date) {
            $isTeam = !empty($b->team_id) || ($b->service && $b->service->isTeamBased());
            $crew = [];

            if ($b->team) {
                $crew = $b->team->workers->map(fn($w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'mobile' => $w->phone_number ?? $w->mobile,
                ])->toArray();
            } elseif ($b->workers && $b->workers->isNotEmpty()) {
                $crew = $b->workers->map(fn($w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'mobile' => $w->phone_number ?? $w->mobile,
                ])->toArray();
            } elseif ($b->worker) {
                $crew = [[
                    'id' => $b->worker->id,
                    'name' => $b->worker->name,
                    'mobile' => $b->worker->phone_number ?? $b->worker->mobile,
                ]];
            }

            // Identify current visit for this date (handles 3-shift / multi-visit subscriptions)
            $currentVisit = null;
            $visitNumber = null;
            $totalVisits = null;
            $visitLabel = null;

            if ($b->visits && $b->visits->isNotEmpty()) {
                $sortedVisits = $b->visits->sortBy('visit_date')->values();
                $totalVisits = $sortedVisits->count();
                $visitIndex = $sortedVisits->search(fn($v) => Carbon::parse($v->visit_date)->toDateString() === $date);
                if ($visitIndex !== false) {
                    $currentVisit = $sortedVisits->get($visitIndex);
                    $visitNumber = $visitIndex + 1;
                    if ($totalVisits > 1) {
                        $visitLabel = app()->getLocale() === 'ar'
                            ? "الزيارة {$visitNumber} من {$totalVisits}"
                            : "Visit {$visitNumber} of {$totalVisits}";
                    }
                }
            }

            $currentStatus = $currentVisit ? $currentVisit->status : $b->status;
            $plannedStart = $currentVisit ? $currentVisit->start_time : $b->start_time;
            $plannedEnd = $currentVisit ? $currentVisit->end_time : $b->end_time;

            $unassignedReason = null;
            if (empty($b->worker_id) && empty($b->team_id) && in_array($currentStatus, ['Upcoming'])) {
                $unassignedReason = app()->getLocale() === 'ar'
                    ? 'بانتظار تعيين مورد مؤهل (عامل أو فريق).'
                    : 'Awaiting qualification and resource assignment (worker or team).';
            }

            if ($currentVisit) {
                $actualStart = $currentVisit->actual_start_time;
                $actualEnd = $currentVisit->actual_end_time;
                $startedByName = $currentVisit->starter
                    ? trim($currentVisit->starter->first_name . ' ' . $currentVisit->starter->last_name)
                    : null;
                $completedByName = $currentVisit->completer
                    ? trim($currentVisit->completer->first_name . ' ' . $currentVisit->completer->last_name)
                    : null;
            } else {
                $actualStart = $b->actual_start_time;
                $actualEnd = $b->actual_end_time;
                $startedByName = $b->starter
                    ? trim($b->starter->first_name . ' ' . $b->starter->last_name)
                    : null;
                $completedByName = $b->completer
                    ? trim($b->completer->first_name . ' ' . $b->completer->last_name)
                    : null;
            }

            $requiredWorkers = $b->number_of_workers ?: ($b->service?->min_workers ?: ($isTeam ? 3 : 1));
            $assignedWorkersCount = count($crew);
            if ($assignedWorkersCount === 0 && !empty($b->worker_id)) {
                $assignedWorkersCount = 1;
            }

            $hasTeam = $isTeam && !empty($b->team_id) && !empty($b->team);
            $hasWorkers = !$isTeam && ($assignedWorkersCount >= $requiredWorkers && $assignedWorkersCount > 0);
            $hasResource = $isTeam ? $hasTeam : $hasWorkers;
            $hasDriver = !empty($groupLookup[$b->id]['driver_id']) && !empty($groupLookup[$b->id]['driver_name']);
            $canStart = $hasDriver && $hasResource;

            $missingReasons = [];
            if (!$hasDriver) {
                $missingReasons[] = app()->getLocale() === 'ar' ? 'سائق النقل' : 'Transport Driver';
            }
            if ($isTeam && !$hasTeam) {
                $missingReasons[] = app()->getLocale() === 'ar' ? 'فريق العمل' : 'Work Team';
            }
            if (!$isTeam && !$hasWorkers) {
                $missingReasons[] = app()->getLocale() === 'ar' ? "العاملات المطلوبة ({$assignedWorkersCount}/{$requiredWorkers})" : "Required Workers ({$assignedWorkersCount}/{$requiredWorkers})";
            }
            $cannotStartReason = empty($missingReasons) ? null : (
                app()->getLocale() === 'ar'
                    ? 'لا يمكن بدء الخدمة قبل تعيين: ' . implode('، ', $missingReasons)
                    : 'Cannot start service until assigned: ' . implode(', ', $missingReasons)
            );

            return [
                'id' => $b->id,
                'booking_ref' => $b->booking_ref,
                'booking_date' => $currentVisit ? $currentVisit->visit_date : $b->booking_date,
                'planned_start_time' => $plannedStart,
                'planned_end_time' => $plannedEnd,
                'actual_start_time' => $actualStart,
                'actual_end_time' => $actualEnd,
                'started_by' => $startedByName,
                'completed_by' => $completedByName,
                'status' => $currentStatus,
                'is_at_risk' => (bool)$b->is_at_risk,
                'at_risk_reason' => $b->at_risk_reason,
                'unassigned_reason' => $unassignedReason,
                'visit_id' => $currentVisit?->id,
                'visit_number' => $visitNumber,
                'total_visits' => $totalVisits,
                'visit_label' => $visitLabel,
                'service' => [
                    'id' => $b->service?->id,
                    'name' => app()->getLocale() === 'ar' ? ($b->service?->name_ar ?: $b->service?->name_en) : ($b->service?->name_en ?: $b->service?->name_ar),
                    'name_ar' => $b->service?->name_ar,
                    'name_en' => $b->service?->name_en,
                    'category' => app()->getLocale() === 'ar' ? ($b->service?->category?->name_ar ?: $b->service?->category?->name_en) : ($b->service?->category?->name_en ?: $b->service?->category?->name_ar),
                    'is_team_based' => $isTeam,
                    'required_workers' => $requiredWorkers,
                ],
                'customer' => [
                    'id' => $b->customer?->id,
                    'name' => $b->customer ? ($b->customer->first_name . ' ' . $b->customer->last_name) : '—',
                    'mobile' => $b->customer?->phone_number ?? '—',
                    'address' => $b->address?->short_address ?? $b->address?->street ?? '—',
                    'service_zone' => $b->address?->serviceZone?->name ?? '—',
                ],
                'team' => $b->team ? [
                    'id' => $b->team->id,
                    'name' => $b->team->name,
                    'code' => $b->team->code,
                    'members_count' => count($crew),
                    'members' => $crew,
                ] : null,
                'worker' => !empty($crew) ? $crew[0] : ($b->worker_id ? [
                    'id' => $b->worker_id,
                    'name' => $b->worker?->name ?? ('Worker #' . $b->worker_id),
                ] : null),
                'crew' => $crew,
                'workers' => $crew,
                'transport' => $groupLookup[$b->id] ?? null,
                'can_start' => $canStart,
                'has_driver' => $hasDriver,
                'has_team' => $hasTeam,
                'has_workers' => $hasWorkers,
                'has_resource' => $hasResource,
                'cannot_start_reason' => $cannotStartReason,
            ];
        });

        return [
            'date' => $date,
            'metrics' => $metrics,
            'bookings' => $bookings,
        ];
    }

    /**
     * Check whether a booking has an active driver assigned to it for a given date.
     */
    public function hasDriverAssigned(Booking $booking, ?string $date = null): bool
    {
        $resolvedDate = $date;
        if (!$resolvedDate) {
            if ($booking->visits()->exists()) {
                $visit = $booking->visits()->whereDate('visit_date', now()->toDateString())->first()
                    ?? $booking->visits()->where('status', 'Upcoming')->orderBy('visit_date')->first()
                    ?? $booking->visits()->orderBy('visit_date')->first();
                $resolvedDate = $visit ? $visit->visit_date : $booking->booking_date;
            } else {
                $resolvedDate = $booking->booking_date;
            }
        }

        // 1. Check if booking is linked directly to a transport group for this date with an active driver
        $hasGroupDriver = DB::table('transport_group_bookings')
            ->join('transport_groups', 'transport_group_bookings.transport_group_id', '=', 'transport_groups.id')
            ->join('drivers', 'transport_groups.driver_id', '=', 'drivers.id')
            ->where('transport_group_bookings.booking_id', $booking->id)
            ->when($resolvedDate, fn($q) => $q->whereDate('transport_groups.service_date', $resolvedDate))
            ->where('drivers.status', 'active')
            ->exists();

        if ($hasGroupDriver) {
            return true;
        }

        // 2. Check if any worker of this booking is assigned to a transport group for this date with an active driver
        $hasWorkerDriver = DB::table('transport_group_workers')
            ->join('transport_groups', 'transport_group_workers.transport_group_id', '=', 'transport_groups.id')
            ->join('drivers', 'transport_groups.driver_id', '=', 'drivers.id')
            ->where('transport_group_workers.booking_id', $booking->id)
            ->when($resolvedDate, fn($q) => $q->whereDate('transport_groups.service_date', $resolvedDate))
            ->where('drivers.status', 'active')
            ->exists();

        return $hasWorkerDriver;
    }

    /**
     * Validate operational readiness: driver on date, team (for team-based), and workers (for worker-based).
     */
    public function validateOperationalReadiness(Booking $booking, ?string $date = null): void
    {
        $resolvedDate = $date;
        if (!$resolvedDate) {
            if ($booking->visits()->exists()) {
                $visit = $booking->visits()->whereDate('visit_date', now()->toDateString())->first()
                    ?? $booking->visits()->where('status', 'Upcoming')->orderBy('visit_date')->first()
                    ?? $booking->visits()->orderBy('visit_date')->first();
                $resolvedDate = $visit ? $visit->visit_date : $booking->booking_date;
            } else {
                $resolvedDate = $booking->booking_date;
            }
        }

        // 1. Driver assignment check for this date
        if (!$this->hasDriverAssigned($booking, $resolvedDate)) {
            throw ValidationException::withMessages([
                'driver' => [app()->getLocale() === 'ar'
                    ? 'لا يمكن بدء الخدمة أو إتمامها قبل تعيين سائق لنقل العاملات في قسم النقل والتوزيع لهذا اليوم.'
                    : 'Cannot start or complete service execution until an active driver has been assigned for transport on this date.'
                ],
            ]);
        }

        // 2. Resource check: Team vs Workers (Commented out per requirement to allow bookings/operations without mandatory workers/team)
        /*
        $isTeam = !empty($booking->team_id) || ($booking->service && $booking->service->isTeamBased());

        if ($isTeam) {
            if (empty($booking->team_id) || !$booking->team) {
                throw ValidationException::withMessages([
                    'team' => [app()->getLocale() === 'ar'
                        ? 'لا يمكن بدء الخدمة أو إتمامها قبل تعيين فريق عمل مؤهل للخدمة.'
                        : 'Cannot start or complete service execution until a qualified team is assigned.'
                    ],
                ]);
            }
        } else {
            $assignedWorkersCount = $booking->workers()->wherePivot('status', 'active')->count();
            if ($assignedWorkersCount === 0 && !empty($booking->worker_id)) {
                $assignedWorkersCount = 1;
            }
            $requiredWorkers = $booking->number_of_workers ?: ($booking->service?->min_workers ?: 1);

            if ($assignedWorkersCount < $requiredWorkers || $assignedWorkersCount === 0) {
                throw ValidationException::withMessages([
                    'workers' => [app()->getLocale() === 'ar'
                        ? "لا يمكن بدء الخدمة أو إتمامها قبل تعيين العاملات المطلوبة (المطلوب: {$requiredWorkers} عاملة، المعين: {$assignedWorkersCount})."
                        : "Cannot start or complete service execution until required workers are assigned ({$assignedWorkersCount}/{$requiredWorkers})."
                    ],
                ]);
            }
        }
        */
    }

    /**
     * US-A34: Start Service Execution (Recording actual_start_time & status -> inService).
     */
    public function startService(Booking $booking, User $user, ?string $actualStart = null, ?string $date = null): Booking
    {
        // Enforce operational readiness: driver on date, team, and workers
        $this->validateOperationalReadiness($booking, $date);
        // Resolve matching visit if visits exist
        $visit = null;
        if ($booking->visits()->exists()) {
            if ($date) {
                $visit = $booking->visits()->whereDate('visit_date', $date)->first();
            }
            if (!$visit) {
                $visit = $booking->visits()->whereDate('visit_date', now()->toDateString())->first()
                    ?? $booking->visits()->where('status', 'Upcoming')->orderBy('visit_date')->first();
            }
        }

        $currentStatus = $visit ? $visit->status : $booking->status;

        if (in_array($currentStatus, ['completed', 'cancelled'])) {
            throw ValidationException::withMessages([
                'status' => [app()->getLocale() === 'ar'
                    ? 'لا يمكن بدء زيارة أو حجز تم اكتماله أو إلغاؤه مسبقًا.'
                    : 'Cannot start a visit or booking that is already completed or cancelled.'
                ],
            ]);
        }

        if ($currentStatus === 'inService') {
            throw ValidationException::withMessages([
                'actual_start_time' => [app()->getLocale() === 'ar'
                    ? 'تم تسجيل بدء الخدمة لهذه الزيارة مسبقًا.'
                    : 'Service was already started for this visit.'
                ],
            ]);
        }

        $startTimeStr = $actualStart ?: now()->format('H:i');
        $oldStatus = $booking->status;

        if ($visit) {
            $visit->update([
                'actual_start_time' => $startTimeStr,
                'started_by' => $user->id,
                'status' => 'inService',
            ]);
        }

        $booking->update([
            'actual_start_time' => $startTimeStr,
            'started_by' => $user->id,
            'status' => 'inService',
        ]);

        $visitNote = $visit ? " (Visit on {$visit->visit_date})" : "";
        BookingHistory::create([
            'booking_id' => $booking->id,
            'old_status' => $oldStatus,
            'new_status' => 'inService',
            'note' => "Service execution started{$visitNote} at {$startTimeStr} by {$user->first_name} {$user->last_name}",
            'created_by' => $user->id,
        ]);

        // Evaluate potential cascade delay if started much later than planned
        $plannedStart = $visit ? $visit->start_time : $booking->start_time;
        if ($plannedStart && $startTimeStr > $plannedStart) {
            $plannedMins = Carbon::parse($plannedStart)->diffInMinutes(Carbon::parse($startTimeStr), false);
            if ($plannedMins > 15) {
                // If delayed by > 15 min, re-evaluate next booking risk
                $this->evaluateRiskForSubsequentBookings($booking);
            }
        }

        return $booking->fresh(['customer', 'service', 'team', 'workers', 'starter', 'visits']);
    }

    /**
     * US-A35: Complete Service Execution (Recording actual_end_time & status -> completed).
     * Triggers US-A36 At-Risk cascade evaluation.
     */
    public function completeService(Booking $booking, User $user, ?string $actualEnd = null, ?string $date = null): Booking
    {
        // Enforce operational readiness: driver on date, team, and workers
        $this->validateOperationalReadiness($booking, $date);

        // Resolve matching visit if visits exist
        $visit = null;
        $hasVisits = $booking->visits()->exists();
        if ($hasVisits) {
            if ($date) {
                $visit = $booking->visits()->whereDate('visit_date', $date)->first();
            }
            if (!$visit) {
                $visit = $booking->visits()->where('status', 'inService')->first()
                    ?? $booking->visits()->whereDate('visit_date', now()->toDateString())->first()
                    ?? $booking->visits()->orderBy('visit_date')->first();
            }
        }

        $currentStatus = $visit ? $visit->status : $booking->status;

        if ($currentStatus === 'completed') {
            throw ValidationException::withMessages([
                'status' => [app()->getLocale() === 'ar'
                    ? 'الزيارة أو الحجز مكتمل بالفعل مسبقًا.'
                    : 'Visit or booking is already completed.'
                ],
            ]);
        }

        $endTimeStr = $actualEnd ?: now()->format('H:i');

        // Verify start time is recorded: cannot update only one time, start and end must belong to same day
        $startComp = $visit
            ? ($visit->actual_start_time ?: $visit->start_time)
            : ($booking->actual_start_time ?: $booking->start_time);

        if (!$startComp) {
            throw ValidationException::withMessages([
                'actual_start_time' => [app()->getLocale() === 'ar'
                    ? 'لا يمكن إنهاء الخدمة بدون وقت البدء. يجب إدخال وقتي البدء والانتهاء معاً في نفس اليوم، لا يمكن تحديث وقت واحد فقط.'
                    : 'Cannot complete service without a start time. Both start and end times must be defined on the same day.'
                ],
            ]);
        }

        if ($endTimeStr <= $startComp) {
            throw ValidationException::withMessages([
                'actual_end_time' => [app()->getLocale() === 'ar'
                    ? 'وقت الانتهاء الفعلي (' . $endTimeStr . ') يجب أن يكون بعد وقت البدء (' . $startComp . ') وفي نفس اليوم.'
                    : 'Actual end time (' . $endTimeStr . ') must be strictly after start time (' . $startComp . ') on the same day.'
                ],
            ]);
        }

        $oldStatus = $booking->status;

        if ($visit) {
            $visit->update([
                'actual_end_time' => $endTimeStr,
                'completed_by' => $user->id,
                'status' => 'completed',
            ]);
        }

        // Check if ALL visits under this parent booking are now completed
        $allVisitsCompleted = true;
        if ($hasVisits) {
            $allVisitsCompleted = !$booking->visits()->where('status', '!=', 'completed')->exists();
        }

        if ($allVisitsCompleted) {
            $booking->update([
                'actual_end_time' => $endTimeStr,
                'completed_by' => $user->id,
                'status' => 'completed',
            ]);

            BookingHistory::create([
                'booking_id' => $booking->id,
                'old_status' => $oldStatus,
                'new_status' => 'completed',
                'note' => "All scheduled visits completed. Service execution completed at {$endTimeStr} by {$user->first_name} {$user->last_name}",
                'created_by' => $user->id,
            ]);

            \App\Service\LoyaltyPointService::awardPointsForCompletedBooking($booking);
        } else {
            // Partial visit completed (e.g., 1 of 3 shifts/visits). Keep booking Upcoming for next visits.
            $booking->update([
                'actual_end_time' => $endTimeStr,
                'completed_by' => $user->id,
                'status' => 'Upcoming',
            ]);

            $visitDateStr = $visit ? $visit->visit_date : 'scheduled visit';
            BookingHistory::create([
                'booking_id' => $booking->id,
                'old_status' => $oldStatus,
                'new_status' => 'Upcoming',
                'note' => "Visit on {$visitDateStr} completed at {$endTimeStr} by {$user->first_name} {$user->last_name}. Remaining scheduled visits exist.",
                'created_by' => $user->id,
            ]);
        }

        // Evaluate At-Risk condition for the subsequent booking of this resource
        $this->evaluateRiskForSubsequentBookings($booking);

        return $booking->fresh(['customer', 'service', 'team', 'workers', 'completer', 'visits']);
    }

    /**
     * Update both actual start time and actual end time together on the same day.
     * Enforces: drivers assigned, both times provided (cannot update one), and same day (end > start).
     */
    public function updateExecutionTimes(Booking $booking, User $user, string $actualStart, string $actualEnd, ?string $date = null): Booking
    {
        // Enforce operational readiness: driver on date, team, and workers
        $this->validateOperationalReadiness($booking, $date);

        if (empty($actualStart) || empty($actualEnd)) {
            throw ValidationException::withMessages([
                'times' => [app()->getLocale() === 'ar'
                    ? 'يجب تحديد وقتي البدء والانتهاء معاً في نفس اليوم، لا يمكن تحديث وقت واحد فقط.'
                    : 'Both start time and end time must be provided together on the same day; cannot update only one.'
                ],
            ]);
        }

        if ($actualEnd <= $actualStart) {
            throw ValidationException::withMessages([
                'actual_end_time' => [app()->getLocale() === 'ar'
                    ? 'وقت الانتهاء الفعلي (' . $actualEnd . ') يجب أن يكون بعد وقت البدء (' . $actualStart . ') وفي نفس اليوم.'
                    : 'Actual end time (' . $actualEnd . ') must be strictly after start time (' . $actualStart . ') on the same day.'
                ],
            ]);
        }

        $visit = null;
        if ($booking->visits()->exists()) {
            if ($date) {
                $visit = $booking->visits()->whereDate('visit_date', $date)->first();
            }
            if (!$visit) {
                $visit = $booking->visits()->whereDate('visit_date', now()->toDateString())->first()
                    ?? $booking->visits()->orderBy('visit_date')->first();
            }
        }

        if ($visit) {
            $visit->update([
                'actual_start_time' => $actualStart,
                'actual_end_time' => $actualEnd,
                'started_by' => $visit->started_by ?: $user->id,
                'completed_by' => $user->id,
                'status' => 'completed',
            ]);
        }

        $allVisitsCompleted = true;
        if ($booking->visits()->exists()) {
            $allVisitsCompleted = !$booking->visits()->where('status', '!=', 'completed')->exists();
        }

        $newStatus = $allVisitsCompleted ? 'completed' : 'Upcoming';
        $oldStatus = $booking->status;

        $booking->update([
            'actual_start_time' => $actualStart,
            'actual_end_time' => $actualEnd,
            'started_by' => $booking->started_by ?: $user->id,
            'completed_by' => $user->id,
            'status' => $newStatus,
        ]);

        BookingHistory::create([
            'booking_id' => $booking->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => "Execution times updated together: {$actualStart} → {$actualEnd} by {$user->first_name} {$user->last_name}",
            'created_by' => $user->id,
        ]);

        if ($allVisitsCompleted) {
            \App\Service\LoyaltyPointService::awardPointsForCompletedBooking($booking);
        }

        $this->evaluateRiskForSubsequentBookings($booking);

        return $booking->fresh(['customer', 'service', 'team', 'workers', 'starter', 'completer', 'visits']);
    }

    /**
     * US-A36: At-Risk & Delay Detection Engine.
     * Evaluates whether a subsequent booking for the same worker or team violates the 60-min transition gap.
     */
    public function evaluateRiskForSubsequentBookings(Booking $booking): ?Booking
    {
        $currentEndTime = $booking->actual_end_time ?: $booking->end_time;
        if (!$currentEndTime) {
            return null;
        }

        // Find the next booking on the same date for the same team or worker
        $nextBookingQuery = Booking::forDate($booking->booking_date)
            ->where('id', '!=', $booking->id)
            ->where('start_time', '>=', $booking->start_time)
            ->whereIn('status', ['Upcoming', 'inService'])
            ->orderBy('start_time', 'asc');

        if ($booking->team_id) {
            $nextBookingQuery->where('team_id', $booking->team_id);
        } elseif ($booking->worker_id) {
            $nextBookingQuery->where('worker_id', $booking->worker_id);
        } else {
            return null;
        }

        $nextBooking = $nextBookingQuery->first();
        if (!$nextBooking) {
            return null;
        }

        // Calculate available transition gap
        $endCarbon = Carbon::parse($booking->booking_date . ' ' . $currentEndTime);
        $nextStartCarbon = Carbon::parse($nextBooking->booking_date . ' ' . $nextBooking->start_time);

        $availableGapMinutes = $endCarbon->diffInMinutes($nextStartCarbon, false);
        $mandatoryGapMinutes = 60; // Document V.17 & V.18 mandate at least 60 minutes

        if ($availableGapMinutes < $mandatoryGapMinutes) {
            $isOverlap = $availableGapMinutes < 0;
            $missingMins = $mandatoryGapMinutes - $availableGapMinutes;

            $resourceName = $booking->team ? ("فريق " . $booking->team->name) : "العامل المكلف";
            $resourceNameEn = $booking->team ? ("Team " . $booking->team->name) : "Assigned Worker";

            if ($isOverlap) {
                $reasonAr = "تأخر انتهاء الحجز السابق #{$booking->booking_ref} للـ ({$resourceName}) حتى الساعة {$currentEndTime}، وتجاوز موعد بدء هذا الحجز ({$nextBooking->start_time}). الفاصل المطلوب 60 دقيقة غير متوفر بالكامل.";
                $reasonEn = "Previous booking #{$booking->booking_ref} for {$resourceNameEn} ended late at {$currentEndTime} and overlapped with this booking ({$nextBooking->start_time}). Mandatory 60-min transition gap is missing.";
            } else {
                $reasonAr = "تأخر انتهاء الحجز السابق #{$booking->booking_ref} للـ ({$resourceName}) حتى الساعة {$currentEndTime}. الفاصل المتاح هو {$availableGapMinutes} دقيقة فقط وهو أقل من الـ 60 دقيقة الإلزامية (نقص بمقدار {$missingMins} دقيقة).";
                $reasonEn = "Previous booking #{$booking->booking_ref} for {$resourceNameEn} ended at {$currentEndTime}. Available transition gap is only {$availableGapMinutes} min, which is less than the mandatory 60 min (deficit of {$missingMins} min).";
            }

            $nextBooking->update([
                'is_at_risk' => true,
                'at_risk_reason' => $reasonAr . "\n" . $reasonEn,
            ]);

            BookingHistory::create([
                'booking_id' => $nextBooking->id,
                'old_status' => $nextBooking->status,
                'new_status' => $nextBooking->status,
                'note' => "Booking marked At-Risk due to transition gap deficit from booking #{$booking->booking_ref}",
                'created_by' => auth()->id() ?? 1,
            ]);
        } else {
            // Gap is >= 60 minutes: if it was at-risk because of this booking, clear it
            if ($nextBooking->is_at_risk && str_contains($nextBooking->at_risk_reason ?? '', $booking->booking_ref)) {
                $nextBooking->update([
                    'is_at_risk' => false,
                    'at_risk_reason' => null,
                ]);

                BookingHistory::create([
                    'booking_id' => $nextBooking->id,
                    'old_status' => $nextBooking->status,
                    'new_status' => $nextBooking->status,
                    'note' => "At-Risk status resolved: valid {$availableGapMinutes} min transition gap verified",
                    'created_by' => auth()->id() ?? 1,
                ]);
            }
        }

        return $nextBooking;
    }

    /**
     * US-A37: Suggest Alternative Resources for At-Risk or Unassigned Bookings.
     */
    public function getAlternativeCandidates(Booking $booking): array
    {
        $hasIndividualWorkers = empty($booking->team_id) && (!empty($booking->worker_id) || $booking->workers()->exists());
        $isTeam = !empty($booking->team_id) || (!$hasIndividualWorkers && $booking->service && $booking->service->isTeamBased());
        $date = $booking->booking_date;
        $start = $booking->start_time;
        $end = $booking->end_time;

        $startMinus60 = Carbon::parse($start)->subMinutes(60)->format('H:i');
        $endPlus60 = Carbon::parse($end)->addMinutes(60)->format('H:i');

        $candidates = [];

        if ($isTeam) {
            // Find teams with 3 members, attached to service, no conflicting booking
            $teams = Team::with(['workers', 'services'])
                ->where('status', 'active')
                ->where('id', '!=', $booking->team_id ?? 0)
                ->whereHas('services', function ($sq) use ($booking) {
                    $sq->where('services.id', $booking->service_id);
                })
                ->get();

            foreach ($teams as $team) {
                if ($team->workers->count() < 3) {
                    continue; // Must have 3 members
                }

                // Skip team if any member is on weekly vacation on this date
                if ($team->workers->contains(fn($w) => !$w->isWorkingOnDate($date))) {
                    continue;
                }

                // Check team booking conflict
                $conflict = Booking::where('team_id', $team->id)
                    ->forDate($date)
                    ->where('id', '!=', $booking->id)
                    ->whereNotIn('status', ['cancelled'])
                    ->where(function ($q) use ($startMinus60, $endPlus60) {
                        $q->whereBetween('start_time', [$startMinus60, $endPlus60])
                          ->orWhereBetween('end_time', [$startMinus60, $endPlus60])
                          ->orWhere(function ($sub) use ($startMinus60, $endPlus60) {
                              $sub->where('start_time', '<=', $startMinus60)
                                  ->where('end_time', '>=', $endPlus60);
                          });
                    })
                    ->exists();

                if (!$conflict) {
                    // Check member absences
                    $workerIds = $team->workers->pluck('id')->toArray();
                    $hasAbsence = WorkerAbsence::whereIn('worker_id', $workerIds)
                        ->where('status', 'reported')
                        ->where(function ($aq) use ($date, $start, $end) {
                            $bStart = Carbon::parse($date . ' ' . $start);
                            $bEnd = Carbon::parse($date . ' ' . $end);
                            $aq->where('absence_start', '<', $bEnd)
                               ->where('absence_end', '>', $bStart);
                        })->exists();

                    if (!$hasAbsence) {
                        $candidates[] = [
                            'type' => 'team',
                            'id' => $team->id,
                            'name' => $team->name,
                            'code' => $team->code,
                            'members_count' => $team->workers->count(),
                            'members' => $team->workers->map(fn($w) => ['id' => $w->id, 'name' => $w->name])->toArray(),
                            'status' => 'available',
                            'is_recommended' => count($candidates) === 0,
                        ];
                    }
                }
            }
        } else {
            // Find individual workers
            $workersQuery = Worker::where('status', 'active')
                ->where('id', '!=', $booking->worker_id ?? 0)
                ->whereHas('services', function ($sq) use ($booking) {
                    $sq->where('services.id', $booking->service_id);
                });

            if (!empty($booking->worker_gender)) {
                $workersQuery->where('gender', strtolower($booking->worker_gender));
            }

            $workers = $workersQuery->get();

            foreach ($workers as $worker) {
                // Skip worker if this date is their weekly vacation
                if (!$worker->isWorkingOnDate($date)) {
                    continue;
                }

                // Check conflict
                $conflict = Booking::where(function ($bq) use ($worker) {
                        $bq->where('worker_id', $worker->id)
                           ->orWhereHas('workers', function ($aw) use ($worker) {
                               $aw->where('workers.id', $worker->id);
                           });
                    })
                    ->forDate($date)
                    ->where('id', '!=', $booking->id)
                    ->whereNotIn('status', ['cancelled'])
                    ->where(function ($q) use ($startMinus60, $endPlus60) {
                        $q->whereBetween('start_time', [$startMinus60, $endPlus60])
                          ->orWhereBetween('end_time', [$startMinus60, $endPlus60])
                          ->orWhere(function ($sub) use ($startMinus60, $endPlus60) {
                              $sub->where('start_time', '<=', $startMinus60)
                                  ->where('end_time', '>=', $endPlus60);
                          });
                    })
                    ->exists();

                if (!$conflict) {
                    $hasAbsence = WorkerAbsence::where('worker_id', $worker->id)
                        ->where('status', 'reported')
                        ->where(function ($aq) use ($date, $start, $end) {
                            $bStart = Carbon::parse($date . ' ' . $start);
                            $bEnd = Carbon::parse($date . ' ' . $end);
                            $aq->where('absence_start', '<', $bEnd)
                               ->where('absence_end', '>', $bStart);
                        })->exists();

                    if (!$hasAbsence) {
                        $candidates[] = [
                            'type' => 'individual',
                            'id' => $worker->id,
                            'name' => $worker->name,
                            'mobile' => $worker->phone_number,
                            'gender' => $worker->gender,
                            'status' => 'available',
                            'is_recommended' => count($candidates) === 0,
                        ];
                    }
                }
            }
        }

        return [
            'booking_id' => $booking->id,
            'booking_ref' => $booking->booking_ref,
            'is_team_based' => $isTeam,
            'service_name' => $booking->service?->name,
            'time_slot' => "{$start} - {$end}",
            'candidates_count' => count($candidates),
            'candidates' => $candidates,
        ];
    }

    /**
     * Resolve At-Risk status by assigning a selected candidate resource.
     */
    public function resolveRiskWithCandidate(Booking $booking, array $data, User $user): Booking
    {
        $candidateType = $data['candidate_type']; // 'team' or 'worker'
        $candidateId = (int)$data['candidate_id'];

        if ($candidateType === 'team') {
            $team = Team::with('workers')->findOrFail($candidateId);
            $booking->update([
                'team_id' => $team->id,
                'worker_id' => null,
                'is_at_risk' => false,
                'at_risk_reason' => null,
            ]);

            // Sync assigned workers
            $booking->workers()->sync($team->workers->pluck('id')->toArray());
        } else {
            $worker = Worker::findOrFail($candidateId);
            $booking->update([
                'worker_id' => $worker->id,
                'team_id' => null,
                'is_at_risk' => false,
                'at_risk_reason' => null,
            ]);

            $booking->workers()->sync([$worker->id]);
        }

        BookingHistory::create([
            'booking_id' => $booking->id,
            'old_status' => $booking->status,
            'new_status' => $booking->status,
            'note' => "Risk resolved: Reassigned to {$candidateType} #{$candidateId} by {$user->first_name} {$user->last_name}",
            'created_by' => $user->id,
        ]);

        return $booking->fresh(['customer', 'service', 'team', 'workers']);
    }

    /**
     * US-A38: Record Worker Absence & Alert Affected Bookings.
     */
    public function recordWorkerAbsence(array $data, User $user): array
    {
        $worker = Worker::findOrFail($data['worker_id']);
        $start = Carbon::parse($data['absence_start']);
        $end = Carbon::parse($data['absence_end']);

        $absence = WorkerAbsence::create([
            'worker_id' => $worker->id,
            'absence_start' => $start,
            'absence_end' => $end,
            'reason' => $data['reason'],
            'status' => 'reported',
            'recorded_by' => $user->id,
            'resolution_notes' => $data['notes'] ?? null,
        ]);

        // Find affected individual bookings
        $affectedIndividualBookings = Booking::where(function ($q) use ($worker) {
                $q->where('worker_id', $worker->id)
                  ->orWhereHas('workers', function ($wq) use ($worker) {
                      $wq->where('workers.id', $worker->id);
                  });
            })
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->forDateBetween($start->toDateString(), $end->toDateString())
            ->get();

        foreach ($affectedIndividualBookings as $b) {
            $reasonAr = "غياب العامل ({$worker->name}) المسجل للفترة من {$start->format('Y-m-d H:i')} إلى {$end->format('Y-m-d H:i')}. السبب: {$absence->reason}";
            $reasonEn = "Assigned worker ({$worker->name}) reported absent from {$start->format('Y-m-d H:i')} to {$end->format('Y-m-d H:i')}. Reason: {$absence->reason}";

            $b->update([
                'is_at_risk' => true,
                'at_risk_reason' => $reasonAr . "\n" . $reasonEn,
            ]);

            BookingHistory::create([
                'booking_id' => $b->id,
                'old_status' => $b->status,
                'new_status' => $b->status,
                'note' => "Marked At-Risk due to reported worker absence of #{$worker->name}",
                'created_by' => $user->id,
            ]);
        }

        // Find affected teams where worker is a member
        $affectedTeamBookings = collect();
        if ($worker->team_id) {
            $team = Team::find($worker->team_id);
            $affectedTeamBookings = Booking::where('team_id', $worker->team_id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->forDateBetween($start->toDateString(), $end->toDateString())
                ->get();

            foreach ($affectedTeamBookings as $tb) {
                $reasonAr = "نقص في قوام فريق ({$team->name}) بسبب غياب العضو ({$worker->name}). السبب: {$absence->reason}. يتطلب تعيين بديل (US-A39).";
                $reasonEn = "Deficit in team ({$team->name}) crew due to absence of member ({$worker->name}). Reason: {$absence->reason}. Requires member replacement (US-A39).";

                $tb->update([
                    'is_at_risk' => true,
                    'at_risk_reason' => $reasonAr . "\n" . $reasonEn,
                ]);

                BookingHistory::create([
                    'booking_id' => $tb->id,
                    'old_status' => $tb->status,
                    'new_status' => $tb->status,
                    'note' => "Team booking marked At-Risk due to member {$worker->name} absence",
                    'created_by' => $user->id,
                ]);
            }
        }

        return [
            'absence' => $absence,
            'worker' => ['id' => $worker->id, 'name' => $worker->name],
            'affected_individual_bookings_count' => $affectedIndividualBookings->count(),
            'affected_team_bookings_count' => $affectedTeamBookings->count(),
            'affected_bookings' => $affectedIndividualBookings->merge($affectedTeamBookings)->map(fn($b) => [
                'id' => $b->id,
                'booking_ref' => $b->booking_ref,
                'date' => $b->booking_date,
                'time_slot' => "{$b->start_time} - {$b->end_time}",
                'service_name' => $b->service?->name,
                'is_at_risk' => true,
            ]),
        ];
    }

    /**
     * Resolve Absence & update status.
     */
    public function resolveAbsence(WorkerAbsence $absence, array $data, User $user): WorkerAbsence
    {
        $absence->update([
            'status' => 'resolved',
            'resolution_notes' => $data['resolution_notes'] ?? 'Resolved by dispatcher',
        ]);

        return $absence;
    }

    /**
     * US-A31: Worker Daily Timeline.
     */
    public function getWorkerTimeline(int $workerId, string $date): array
    {
        $worker = Worker::with('team')->findOrFail($workerId);

        $bookings = Booking::with([
                'visits',
                'service',
                'customer',
                'address.serviceZone',
            ])->where(function ($q) use ($workerId) {
                $q->where('worker_id', $workerId)
                  ->orWhereHas('workers', function ($wq) use ($workerId) {
                      $wq->where('workers.id', $workerId);
                  });
            })
            ->forDate($date)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $absences = WorkerAbsence::where('worker_id', $workerId)
            ->whereDate('absence_start', '<=', $date)
            ->whereDate('absence_end', '>=', $date)
            ->get();

        $sortedBookings = $bookings->sortBy(function ($b) use ($date) {
            $visit = $b->visits ? $b->visits->first(fn($v) => Carbon::parse($v->visit_date)->toDateString() === $date) : null;
            return $visit ? $visit->start_time : $b->start_time;
        });

        $items = [];
        $prevEnd = null;

        foreach ($sortedBookings as $b) {
            $visit = $b->visits ? $b->visits->first(fn($v) => Carbon::parse($v->visit_date)->toDateString() === $date) : null;
            $pStart = $visit ? $visit->start_time : $b->start_time;
            $pEnd = $visit ? $visit->end_time : $b->end_time;
            $bStatus = $visit ? $visit->status : $b->status;

            if ($prevEnd) {
                $gap = Carbon::parse($prevEnd)->diffInMinutes(Carbon::parse($pStart), false);
                $items[] = [
                    'type' => 'transition_gap',
                    'start_time' => $prevEnd,
                    'end_time' => $pStart,
                    'gap_minutes' => $gap,
                    'is_valid' => $gap >= 60,
                ];
            }

            $actStart = $visit ? $visit->actual_start_time : $b->actual_start_time;
            $actEnd = $visit ? $visit->actual_end_time : $b->actual_end_time;

            $serviceName = app()->getLocale() === 'ar'
                ? ($b->service?->name_ar ?: $b->service?->name_en)
                : ($b->service?->name_en ?: $b->service?->name_ar);

            $custName = $b->customer
                ? trim(($b->customer->first_name ?? '') . ' ' . ($b->customer->last_name ?? ''))
                : '—';

            $addrText = $b->address?->short_address
                ?? $b->address?->street
                ?? ($b->address?->serviceZone?->name)
                ?? '—';

            $items[] = [
                'type' => 'service',
                'booking_id' => $b->id,
                'booking_ref' => $b->booking_ref,
                'service_id' => $b->service_id,
                'service_name' => $serviceName ?: ($b->service?->name_ar ?: $b->service?->name_en ?: 'خدمة'),
                'service_name_ar' => $b->service?->name_ar,
                'service_name_en' => $b->service?->name_en,
                'customer_name' => $custName ?: '—',
                'customer_mobile' => $b->customer?->phone_number ?? '—',
                'address' => $addrText,
                'service_zone' => $b->address?->serviceZone?->name ?? '—',
                'planned_start' => $pStart,
                'planned_end' => $pEnd,
                'actual_start' => $actStart,
                'actual_end' => $actEnd,
                'status' => $bStatus,
                'is_at_risk' => (bool)$b->is_at_risk,
                'at_risk_reason' => $b->at_risk_reason,
            ];

            $prevEnd = $actEnd ?: $pEnd;
        }

        return [
            'worker' => [
                'id' => $worker->id,
                'name' => $worker->name,
                'gender' => $worker->gender,
                'mobile' => $worker->phone_number ?? $worker->mobile,
                'nationality' => $worker->nationality,
                'team_name' => $worker->team?->name,
            ],
            'date' => $date,
            'timeline' => $items,
            'absences' => $absences,
        ];
    }
}
