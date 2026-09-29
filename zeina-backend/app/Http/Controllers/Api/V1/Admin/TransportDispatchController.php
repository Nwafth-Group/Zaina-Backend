<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignWorkerDispatchRequest;
use App\Http\Requests\Api\V1\Admin\UnassignWorkerDispatchRequest;
use App\Http\Requests\Api\V1\Admin\UpdateGroupDispatchRequest;
use App\Http\Resources\Api\V1\TransportGroup\TransportGroupResource;
use App\Models\TransportGroup;
use App\Service\Admin\TransportDispatchService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class TransportDispatchController extends Controller
{
    use ApiResponseTrait;

    public function __construct(public TransportDispatchService $dispatchService) {}

    /**
     * Get shift-based list for a date.
     */
    public function shiftsOverview(Request $request)
    {
        $date = $request->input('service_date');
        $filters = $request->only(['governorate', 'area', 'status', 'start_time']);

        $data = $this->dispatchService->getShiftsOverview($date, $filters);

        return $this->successResponse($data);
    }

    /**
     * Get shift details with individual bookings, workers, and driver manifests.
     */
    public function shiftDetails(Request $request)
    {
        $request->validate([
            'service_date' => 'required|date',
            'start_time' => 'required|string',
        ]);

        $date = $request->input('service_date');
        $startTime = $request->input('start_time');
        $filters = $request->only(['governorate', 'area']);

        $data = $this->dispatchService->getShiftDetails($date, $startTime, $filters);

        return $this->successResponse($data);
    }

    /**
     * Assign a single worker to a driver/transport group.
     */
    public function assignWorker(AssignWorkerDispatchRequest $request)
    {
        $result = $this->dispatchService->assignWorkerToDriver($request->validated());

        return $this->successResponse(
            $result,
            __('messages.transport_worker_assigned_successfully') ?? 'تم تعيين العاملة للسائق بنجاح'
        );
    }

    /**
     * Unassign a worker from a driver/transport group.
     */
    public function unassignWorker(UnassignWorkerDispatchRequest $request)
    {
        $bookingId = (int) $request->input('booking_id');
        $workerId = (int) $request->input('worker_id');
        $transportGroupId = $request->filled('transport_group_id') ? (int) $request->input('transport_group_id') : null;

        $this->dispatchService->unassignWorker($bookingId, $workerId, $transportGroupId);

        return $this->successResponse(
            null,
            __('messages.transport_worker_unassigned_successfully') ?? 'تم إلغاء تعيين العاملة بنجاح'
        );
    }

    /**
     * Export manifest for a specific driver's trip.
     */
    public function exportDriverManifest($id)
    {
        return $this->dispatchService->exportDriverManifestExcel((int) $id);
    }

    /**
     * Export manifest for all drivers in a shift.
     */
    public function exportShiftManifest(Request $request)
    {
        $request->validate([
            'service_date' => 'required|date',
            'start_time' => 'required|string',
        ]);

        $date = $request->input('service_date');
        $startTime = $request->input('start_time');

        return $this->dispatchService->exportShiftManifestExcel($date, $startTime);
    }

    // --- Legacy / Backward-Compatible Endpoints ---

    public function dailyOverview(Request $request)
    {
        $date = $request->filled('service_date') ? $request->input('service_date') : null;
        $governorate = $request->input('governorate');
        $area = $request->input('area');
        $startTime = $request->input('start_time');
        $status = $request->input('status');

        $data = $this->dispatchService->getDailyOverview($date, $governorate, $area, $startTime, $status);

        return $this->successResponse($data);
    }

    public function regionGroupDetails(Request $request)
    {
        $request->validate([
            'service_date' => 'required|date',
            'governorate' => 'required|string',
            'area' => 'required|string',
        ]);

        $date = $request->input('service_date');
        $governorate = $request->input('governorate');
        $area = $request->input('area');

        $data = $this->dispatchService->getRegionGroupDetails($date, $governorate, $area);

        return $this->successResponse($data);
    }

    public function updateGroupDispatch(UpdateGroupDispatchRequest $request, $id)
    {
        $group = TransportGroup::findOrFail($id);
        $updatedGroup = $this->dispatchService->updateGroupDispatch($group, $request->validated());

        return $this->successResponse(
            new TransportGroupResource($updatedGroup),
            __('messages.transport_dispatch_updated_successfully')
        );
    }

    public function exportGroupExcel($id)
    {
        return $this->dispatchService->exportGroupExcel((int) $id);
    }
}
