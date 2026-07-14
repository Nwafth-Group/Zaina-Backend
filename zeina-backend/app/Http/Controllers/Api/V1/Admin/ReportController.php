<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Reports\AdminReportDetailResource;
use App\Http\Resources\Api\V1\Reports\ReportsResource;
use App\Models\Report;
use App\Models\ReportHistory;
use App\Models\User;
use App\Notifications\ReportNotification;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $query = Report::with(['booking', 'customer', 'service', 'worker', 'assignedAdmin']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('service_id')) {
            $query->where('service_id', $request->input('service_id'));
        }

        if ($request->filled('worker_id')) {
            $query->where('worker_id', $request->input('worker_id'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->input('booking_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $reports = $query->latest()->paginate($request->input('per_page', 15));

        return $this->successResponse([
            'items' => ReportsResource::collection($reports->items()),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ]
        ], 'Reports retrieved successfully');
    }

    public function show(Report $report)
    {
        $report->load(['booking', 'customer', 'service', 'worker', 'assignedAdmin', 'histories.createdBy']);
        return $this->successResponse(new AdminReportDetailResource($report), 'Report details retrieved successfully');
    }

    public function assign(Request $request, Report $report)
    {
        $request->validate([
            'admin_id' => 'required|exists:users,id',
        ]);

        $adminId = $request->input('admin_id');
        $adminUser = User::findOrFail($adminId);

        // Verify the user is an admin or operation employee
        if (!in_array($adminUser->type, ['super_admin', 'operation'])) {
            return response()->json([
                'success' => false,
                'message' => 'The selected user is not an administrator or support officer.',
            ], 422);
        }

        $report->update([
            'assigned_admin_id' => $adminId,
        ]);

        return $this->successResponse(
            new AdminReportDetailResource($report->load(['booking', 'customer', 'service', 'worker', 'assignedAdmin', 'histories.createdBy'])),
            'Report assigned successfully'
        );
    }

    public function updateStatus(Request $request, Report $report)
    {
        $request->validate([
            'status' => 'required|string|in:submitted,under_review,in_progress,resolved,rejected',
            'note' => 'nullable|string|max:1000',
        ]);

        $newStatus = $request->input('status');
        $oldStatus = $report->status;
        $note = $request->input('note');

        if ($oldStatus === $newStatus) {
            return response()->json([
                'success' => false,
                'message' => 'The report is already in this status.',
            ], 422);
        }

        $validTransitions = [
            'submitted'    => ['under_review', 'rejected'],
            'under_review' => ['in_progress', 'rejected'],
            'in_progress'  => ['resolved', 'rejected'],
            'resolved'     => [], // Terminal
            'rejected'     => [], // Terminal
        ];

        if (!in_array($newStatus, $validTransitions[$oldStatus] ?? [], true)) {
            $msg = "Cannot change report status from '{$oldStatus}' to '{$newStatus}'. Status changes must follow a sequential flow: Submitted -> Under Review -> In Progress -> Resolved / Rejected.";
            $msgAr = "لا يمكن تغيير حالة البلاغ من '{$oldStatus}' إلى '{$newStatus}'. يجب أن تتبع التحديثات تسلسلاً: مقدم -> قيد المراجعة -> قيد المعالجة -> تم الحل / مرفوض.";
            return response()->json([
                'success' => false,
                'message' => app()->getLocale() === 'ar' ? $msgAr : $msg,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $report->update([
                'status' => $newStatus,
            ]);

            ReportHistory::create([
                'report_id' => $report->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'note' => $note,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            // Notify the customer of status updates
            try {
                $report->customer->notify(new ReportNotification($report, 'status_changed'));
            } catch (\Exception $ex) {
                \Illuminate\Support\Facades\Log::error('Failed to notify customer of report status update: ' . $ex->getMessage());
            }

            return $this->successResponse(
                new AdminReportDetailResource($report->load(['booking', 'customer', 'service', 'worker', 'assignedAdmin', 'histories.createdBy'])),
                'Report status updated successfully'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update report status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function addNote(Request $request, Report $report)
    {
        $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $note = $request->input('note');

        // Append to admin notes
        $currentNotes = $report->admin_notes ? $report->admin_notes . "\n" : "";
        $report->update([
            'admin_notes' => $currentNotes . "[" . now()->toDateTimeString() . " by " . auth()->user()->first_name . "]: " . $note,
        ]);

        return $this->successResponse(
            new AdminReportDetailResource($report->load(['booking', 'customer', 'service', 'worker', 'assignedAdmin', 'histories.createdBy'])),
            'Internal notes added successfully'
        );
    }
}
