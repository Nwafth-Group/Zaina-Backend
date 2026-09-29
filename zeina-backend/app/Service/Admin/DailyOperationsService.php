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
            'workers',
            'team.workers',
            'address.serviceZone',
            'starter',
            'completer',
        ])->where('booking_date', $date);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
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

        $allDayBookings = Booking::where('booking_date', $date)->get();

        // Calculate KPI Metrics
        $metrics = [
            'total_bookings' => $allDayBookings->count(),
            'upcoming' => $allDayBookings->where('status', 'Upcoming')->count(),
            'in_service' => $allDayBookings->where('status', 'inService')->count(),
            'completed' => $allDayBookings->where('status', 'completed')->count(),
            'cancelled' => $allDayBookings->where('status', 'cancelled')->count(),
            'at_risk' => $allDayBookings->where('is_at_risk', true)->count(),
            'unassigned' => $allDayBookings->filter(function ($b) {
                return in_array($b->status, ['Upcoming']) && empty($b->worker_id) && empty($b->team_id);
            })->count(),
            'active_teams' => $allDayBookings->whereNotNull('team_id')->pluck('team_id')->unique()->count(),
            'active_workers' => $allDayBookings->whereNotNull('worker_id')->pluck('worker_id')->unique()->count(),
        ];

        // Fetch Transport Groups and Group Workers for this date
        $transportGroups = TransportGroup::with(['driver', 'departurePoint', 'bookings', 'groupWorkers'])
            ->whereDate('service_date', $date)
            ->get();

        $groupLookup = [];
        foreach ($transportGroups as $tg) {
            foreach ($tg->bookings as $b) {
                $groupLookup[$b->id] = [
                    'group_id' => $tg->id,
                    'start_time' => $tg->start_time,
                    'departure_time' => $tg->departure_time,
                    'departure_point' => $tg->departurePoint?->name,
                    'driver_id' => $tg->driver?->id,
                    'driver_name' => $tg->driver?->name,
                    'driver_phone' => $tg->driver?->mobile ?? $tg->driver?->phone,
                    'vehicle_type' => $tg->vehicle_type ?? $tg->driver?->vehicle_type,
                    'vehicle_plate' => $tg->vehicle_plate ?? $tg->driver?->vehicle_plate,
                    'vehicle_capacity' => $tg->vehicle_capacity ?? $tg->driver?->vehicle_capacity ?? 5,
                ];
            }
            foreach ($tg->groupWorkers as $gw) {
                if ($gw->booking_id && !isset($groupLookup[$gw->booking_id])) {
                    $groupLookup[$gw->booking_id] = [
                        'group_id' => $tg->id,
                        'start_time' => $tg->start_time,
                        'departure_time' => $tg->departure_time,
                        'departure_point' => $tg->departurePoint?->name,
                        'driver_id' => $tg->driver?->id,
                        'driver_name' => $tg->driver?->name,
                        'driver_phone' => $tg->driver?->mobile ?? $tg->driver?->phone,
                        'vehicle_type' => $tg->vehicle_type ?? $tg->driver?->vehicle_type,
                        'vehicle_plate' => $tg->vehicle_plate ?? $tg->driver?->vehicle_plate,
                        'vehicle_capacity' => $tg->vehicle_capacity ?? $tg->driver?->vehicle_capacity ?? 5,
                    ];
                }
            }
        }

        $bookings = $query->orderBy('start_time', 'asc')->get()->map(function ($b) use ($groupLookup) {
            $isTeam = !empty($b->team_id) || ($b->service && $b->service->isTeamBased());
            $crew = [];

            if ($b->team) {
                $crew = $b->team->workers->map(fn($w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'mobile' => $w->phone_number ?? $w->mobile,
                ])->toArray();
            } elseif ($b->workers->isNotEmpty()) {
                $crew = $b->workers->map(fn($w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'mobile' => $w->phone_number ?? $w->mobile,
                ])->toArray();
            }

            $unassignedReason = null;
            if (empty($b->worker_id) && empty($b->team_id) && in_array($b->status, ['Upcoming'])) {
                $unassignedReason = app()->getLocale() === 'ar'
                    ? 'بانتظار تعيين مورد مؤهل (عامل أو فريق).'
                    : 'Awaiting qualification and resource assignment (worker or team).';
            }

            return [
                'id' => $b->id,
                'booking_ref' => $b->booking_ref,
                'booking_date' => $b->booking_date,
                'planned_start_time' => $b->start_time,
                'planned_end_time' => $b->end_time,
                'actual_start_time' => $b->actual_start_time,
                'actual_end_time' => $b->actual_end_time,
                'started_by' => $b->starter?->first_name ? ($b->starter->first_name . ' ' . $b->starter->last_name) : null,
                'completed_by' => $b->completer?->first_name ? ($b->completer->first_name . ' ' . $b->completer->last_name) : null,
                'status' => $b->status,
                'is_at_risk' => (bool)$b->is_at_risk,
                'at_risk_reason' => $b->at_risk_reason,
                'unassigned_reason' => $unassignedReason,
                'service' => [
                    'id' => $b->service?->id,
                    'name' => app()->getLocale() === 'ar' ? ($b->service?->name_ar ?: $b->service?->name_en) : ($b->service?->name_en ?: $b->service?->name_ar),
                    'name_ar' => $b->service?->name_ar,
                    'name_en' => $b->service?->name_en,
                    'category' => app()->getLocale() === 'ar' ? ($b->service?->category?->name_ar ?: $b->service?->category?->name_en) : ($b->service?->category?->name_en ?: $b->service?->category?->name_ar),
                    'is_team_based' => $isTeam,
                    'required_workers' => $b->service?->required_workers ?? ($isTeam ? 3 : 1),
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
                'worker' => $b->worker_id ? [
                    'id' => $b->worker_id,
                    'name' => $crew[0]['name'] ?? 'Worker #' . $b->worker_id,
                ] : null,
                'crew' => $crew,
                'transport' => $groupLookup[$b->id] ?? null,
            ];
        });

        return [
            'date' => $date,
            'metrics' => $metrics,
            'bookings' => $bookings,
        ];
    }

    /**
     * US-A34: Start Service Execution (Recording actual_start_time & status -> inService).
     */
    public function startService(Booking $booking, User $user, ?string $actualStart = null): Booking
    {
        if (in_array($booking->status, ['completed', 'cancelled'])) {
            throw ValidationException::withMessages([
                'status' => [app()->getLocale() === 'ar'
                    ? 'لا يمكن بدء حجز تم اكتماله أو إلغاؤه مسبقًا.'
                    : 'Cannot start a booking that is already completed or cancelled.'
                ],
            ]);
        }

        if (!empty($booking->actual_start_time) && $booking->status === 'inService') {
            throw ValidationException::withMessages([
                'actual_start_time' => [app()->getLocale() === 'ar'
                    ? 'تم تسجيل بدء الخدمة مسبقًا في تمام الساعة ' . $booking->actual_start_time
                    : 'Service was already started at ' . $booking->actual_start_time
                ],
            ]);
        }

        $startTimeStr = $actualStart ?: now()->format('H:i');
        $oldStatus = $booking->status;

        $booking->update([
            'actual_start_time' => $startTimeStr,
            'started_by' => $user->id,
            'status' => 'inService',
        ]);

        BookingHistory::create([
            'booking_id' => $booking->id,
            'old_status' => $oldStatus,
            'new_status' => 'inService',
            'note' => "Service execution started at {$startTimeStr} by {$user->first_name} {$user->last_name}",
            'created_by' => $user->id,
        ]);

        // Evaluate potential cascade delay if started much later than planned
        if ($booking->start_time && $startTimeStr > $booking->start_time) {
            $plannedMins = Carbon::parse($booking->start_time)->diffInMinutes(Carbon::parse($startTimeStr), false);
            if ($plannedMins > 15) {
                // If delayed by > 15 min, re-evaluate next booking risk
                $this->evaluateRiskForSubsequentBookings($booking);
            }
        }

        return $booking->fresh(['customer', 'service', 'team', 'workers', 'starter']);
    }

    /**
     * US-A35: Complete Service Execution (Recording actual_end_time & status -> completed).
     * Triggers US-A36 At-Risk cascade evaluation.
     */
    public function completeService(Booking $booking, User $user, ?string $actualEnd = null): Booking
    {
        if ($booking->status === 'completed') {
            throw ValidationException::withMessages([
                'status' => [app()->getLocale() === 'ar'
                    ? 'الحجز مكتمل بالفعل مسبقًا.'
                    : 'Booking is already completed.'
                ],
            ]);
        }

        $endTimeStr = $actualEnd ?: now()->format('H:i');

        // Verify end time comes after start time
        $startComp = $booking->actual_start_time ?: $booking->start_time;
        if ($startComp && $endTimeStr <= $startComp) {
            throw ValidationException::withMessages([
                'actual_end_time' => [app()->getLocale() === 'ar'
                    ? 'وقت الانتهاء الفعلي (' . $endTimeStr . ') يجب أن يكون بعد وقت البدء (' . $startComp . ').'
                    : 'Actual end time (' . $endTimeStr . ') must be strictly after start time (' . $startComp . ').'
                ],
            ]);
        }

        $oldStatus = $booking->status;

        $booking->update([
            'actual_end_time' => $endTimeStr,
            'completed_by' => $user->id,
            'status' => 'completed',
        ]);

        BookingHistory::create([
            'booking_id' => $booking->id,
            'old_status' => $oldStatus,
            'new_status' => 'completed',
            'note' => "Service execution completed at {$endTimeStr} by {$user->first_name} {$user->last_name}",
            'created_by' => $user->id,
        ]);

        // Evaluate At-Risk condition for the subsequent booking of this resource
        $this->evaluateRiskForSubsequentBookings($booking);

        return $booking->fresh(['customer', 'service', 'team', 'workers', 'completer']);
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
        $nextBookingQuery = Booking::where('booking_date', $booking->booking_date)
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
        $isTeam = !empty($booking->team_id) || ($booking->service && $booking->service->isTeamBased());
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

                // Check team booking conflict
                $conflict = Booking::where('team_id', $team->id)
                    ->where('booking_date', $date)
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
                // Check conflict
                $conflict = Booking::where(function ($bq) use ($worker) {
                        $bq->where('worker_id', $worker->id)
                           ->orWhereHas('workers', function ($aw) use ($worker) {
                               $aw->where('workers.id', $worker->id);
                           });
                    })
                    ->where('booking_date', $date)
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
            ->whereBetween('booking_date', [$start->toDateString(), $end->toDateString()])
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
                ->whereBetween('booking_date', [$start->toDateString(), $end->toDateString()])
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

        $bookings = Booking::where(function ($q) use ($workerId) {
                $q->where('worker_id', $workerId)
                  ->orWhereHas('workers', function ($wq) use ($workerId) {
                      $wq->where('workers.id', $workerId);
                  });
            })
            ->where('booking_date', $date)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('start_time', 'asc')
            ->get();

        $absences = WorkerAbsence::where('worker_id', $workerId)
            ->whereDate('absence_start', '<=', $date)
            ->whereDate('absence_end', '>=', $date)
            ->get();

        $items = [];
        $prevEnd = null;

        foreach ($bookings as $b) {
            if ($prevEnd) {
                $gap = Carbon::parse($prevEnd)->diffInMinutes(Carbon::parse($b->start_time), false);
                $items[] = [
                    'type' => 'transition_gap',
                    'start_time' => $prevEnd,
                    'end_time' => $b->start_time,
                    'gap_minutes' => $gap,
                    'is_valid' => $gap >= 60,
                ];
            }

            $items[] = [
                'type' => 'service',
                'booking_id' => $b->id,
                'booking_ref' => $b->booking_ref,
                'service_name' => $b->service?->name,
                'customer_name' => $b->customer ? ($b->customer->first_name . ' ' . $b->customer->last_name) : '—',
                'address' => $b->address?->short_address ?? '—',
                'planned_start' => $b->start_time,
                'planned_end' => $b->end_time,
                'actual_start' => $b->actual_start_time,
                'actual_end' => $b->actual_end_time,
                'status' => $b->status,
                'is_at_risk' => (bool)$b->is_at_risk,
                'at_risk_reason' => $b->at_risk_reason,
            ];

            $prevEnd = $b->actual_end_time ?: $b->end_time;
        }

        return [
            'worker' => ['id' => $worker->id, 'name' => $worker->name, 'gender' => $worker->gender],
            'date' => $date,
            'timeline' => $items,
            'absences' => $absences,
        ];
    }
}
