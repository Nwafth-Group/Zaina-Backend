<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreDriverRequest;
use App\Http\Requests\Api\V1\Admin\UpdateDriverRequest;
use App\Http\Resources\Api\V1\Driver\DriverResource;
use App\Models\Driver;
use App\Service\Admin\DriverService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    use ApiResponseTrait;

    public function __construct(public DriverService $driverService) {}

    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 15);
        $drivers = $this->driverService->getPaginated($request->all(), $perPage);
        $counts = $this->driverService->getCounts($request->all());

        return $this->successResponse([
            'items' => DriverResource::collection($drivers),
            'meta' => [
                'current_page' => $drivers->currentPage(),
                'last_page' => $drivers->lastPage(),
                'per_page' => $drivers->perPage(),
                'total' => $drivers->total(),
            ],
            'counts' => $counts,
        ]);
    }

    public function store(StoreDriverRequest $request)
    {
        $validated = $request->validated();
        $imageFile = $request->file('image');

        $driver = $this->driverService->createDriver($validated, $imageFile);

        return $this->successResponse(
            new DriverResource($driver),
            __('messages.driver_saved_successfully'),
            201
        );
    }

    public function show($id)
    {
        $driver = Driver::findOrFail($id);
        return $this->successResponse(new DriverResource($driver));
    }

    public function update(UpdateDriverRequest $request, $id)
    {
        $driver = Driver::findOrFail($id);
        $validated = $request->validated();
        $imageFile = $request->file('image');

        $updatedDriver = $this->driverService->updateDriver($driver, $validated, $imageFile);

        return $this->successResponse(
            new DriverResource($updatedDriver),
            __('messages.driver_updated_successfully')
        );
    }

    public function destroy($id)
    {
        $driver = Driver::findOrFail($id);
        $this->driverService->deleteDriver($driver);

        return $this->successResponse(null, __('messages.driver_deleted_successfully'));
    }
}
