<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\WorkerAbsence;
use App\Service\Admin\DailyOperationsService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class DailyOperationsController extends Controller
{
    use ApiResponseTrait;

    protected DailyOperationsService $operationsService;

    public function __construct(DailyOperationsService $operationsService)
    {
        $this->operationsService = $operationsService;
    }

    /**
     * US-A30: Daily Operations Master Dashboard.
     */
    public function dailyDashboard(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $filters = $request->only(['status', 'is_at_risk', 'service_id', 'team_id', 'search']);

        $data = $this->operationsService->getDailyDashboard($date, $filters);

        return $this->successResponse($data, 'Daily operations fetched successfully');
    }

    /**
     * US-A34: Start Service Execution (Recording actual_start_time).
     */
    public function startService(Request $request, Booking $booking)
    {
        $request->validate([
            'actual_start_time' => 'nullable|string|regex:/^\d{1,2}:\d{2}$/',
        ]);

        $updatedBooking = $this->operationsService->startService(
            $booking,
            $request->user(),
            $request->input('actual_start_time')
        );

        return $this->successResponse($updatedBooking, __('messages.service_started_successfully') ?: 'Service execution started successfully');
    }

    /**
     * US-A35: Complete Service Execution (Recording actual_end_time & cascade at-risk evaluation).
     */
    public function completeService(Request $request, Booking $booking)
    {
        $request->validate([
            'actual_end_time' => 'nullable|string|regex:/^\d{1,2}:\d{2}$/',
        ]);

        $updatedBooking = $this->operationsService->completeService(
            $booking,
            $request->user(),
            $request->input('actual_end_time')
        );

        return $this->successResponse($updatedBooking, __('messages.service_completed_successfully') ?: 'Service execution completed successfully');
    }

    /**
     * US-A37: Suggest Alternative Candidates for At-Risk / Unassigned Bookings.
     */
    public function getAlternatives(Booking $booking)
    {
        $alternatives = $this->operationsService->getAlternativeCandidates($booking);

        return $this->successResponse($alternatives, 'Alternative candidates fetched successfully');
    }

    /**
     * Resolve At-Risk status with a selected candidate.
     */
    public function resolveRisk(Request $request, Booking $booking)
    {
        $request->validate([
            'candidate_type' => 'required|in:team,worker',
            'candidate_id' => 'required|integer',
        ]);

        $resolved = $this->operationsService->resolveRiskWithCandidate($booking, $request->all(), $request->user());

        return $this->successResponse($resolved, __('messages.risk_resolved_successfully') ?: 'Resource reassigned and risk resolved successfully');
    }

    /**
     * US-A38: Record Worker Absence & Alert Affected Bookings.
     */
    public function recordAbsence(Request $request)
    {
        $request->validate([
            'worker_id' => 'required|exists:workers,id',
            'absence_start' => 'required|date',
            'absence_end' => 'required|date|after_or_equal:absence_start',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $result = $this->operationsService->recordWorkerAbsence($request->all(), $request->user());

        return $this->successResponse($result, __('messages.worker_absence_recorded') ?: 'Worker absence recorded and affected bookings updated');
    }

    /**
     * Get Absences List.
     */
    public function getAbsences(Request $request)
    {
        $absences = WorkerAbsence::with(['worker', 'recorder'])
            ->when($request->filled('worker_id'), fn($q) => $q->where('worker_id', $request->worker_id))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($absences, 'Worker absences fetched successfully');
    }

    /**
     * Resolve Worker Absence.
     */
    public function resolveAbsence(Request $request, WorkerAbsence $absence)
    {
        $resolved = $this->operationsService->resolveAbsence($absence, $request->all(), $request->user());

        return $this->successResponse($resolved, 'Worker absence marked as resolved');
    }

    /**
     * US-A31: Worker Daily Timeline.
     */
    public function workerTimeline(Request $request)
    {
        $request->validate([
            'worker_id' => 'required|exists:workers,id',
            'date' => 'required|date',
        ]);

        $timeline = $this->operationsService->getWorkerTimeline((int)$request->worker_id, $request->date);

        return $this->successResponse($timeline, 'Worker timeline fetched successfully');
    }
}
