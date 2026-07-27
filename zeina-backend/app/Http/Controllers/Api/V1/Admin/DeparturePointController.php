<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreDeparturePointRequest;
use App\Http\Requests\Api\V1\Admin\UpdateDeparturePointRequest;
use App\Http\Resources\Api\V1\DeparturePoint\DeparturePointResource;
use App\Models\DeparturePoint;
use App\Service\Admin\DeparturePointService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class DeparturePointController extends Controller
{
    use ApiResponseTrait;

    public function __construct(public DeparturePointService $pointService) {}

    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 15);
        $points = $this->pointService->getPaginated($request->all(), $perPage);
        $counts = $this->pointService->getCounts();

        return $this->successResponse([
            'items' => DeparturePointResource::collection($points),
            'meta' => [
                'current_page' => $points->currentPage(),
                'last_page' => $points->lastPage(),
                'per_page' => $points->perPage(),
                'total' => $points->total(),
            ],
            'counts' => $counts,
        ]);
    }

    public function store(StoreDeparturePointRequest $request)
    {
        $point = $this->pointService->createPoint($request->validated());

        return $this->successResponse(
            new DeparturePointResource($point),
            __('messages.departure_point_saved_successfully'),
            201
        );
    }

    public function show($id)
    {
        $point = DeparturePoint::findOrFail($id);
        return $this->successResponse(new DeparturePointResource($point));
    }

    public function update(UpdateDeparturePointRequest $request, $id)
    {
        $point = DeparturePoint::findOrFail($id);
        $updatedPoint = $this->pointService->updatePoint($point, $request->validated());

        return $this->successResponse(
            new DeparturePointResource($updatedPoint),
            __('messages.departure_point_updated_successfully')
        );
    }

    public function destroy($id)
    {
        $point = DeparturePoint::findOrFail($id);
        $this->pointService->deletePoint($point);

        return $this->successResponse(null, __('messages.departure_point_deleted_successfully'));
    }
}
