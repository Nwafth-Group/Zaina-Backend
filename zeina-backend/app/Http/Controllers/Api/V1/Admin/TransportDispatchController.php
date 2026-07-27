<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
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

    public function dailyOverview(Request $request)
    {
        $date = $request->input('service_date', date('Y-m-d'));
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
}
