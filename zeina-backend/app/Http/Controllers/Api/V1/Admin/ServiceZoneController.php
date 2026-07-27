<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreServiceZoneRequest;
use App\Http\Requests\Api\V1\Admin\UpdateServiceZoneRequest;
use App\Http\Resources\Api\V1\ServiceZone\ServiceZoneResource;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Service\Admin\ServiceZoneService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ServiceZoneController extends Controller
{
    use ApiResponseTrait;

    public function __construct(public ServiceZoneService $zoneService) {}

    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 15);
        $zones = $this->zoneService->getPaginated($request->all(), $perPage);
        $counts = $this->zoneService->getCounts();

        return $this->successResponse([
            'items' => ServiceZoneResource::collection($zones),
            'meta' => [
                'current_page' => $zones->currentPage(),
                'last_page' => $zones->lastPage(),
                'per_page' => $zones->perPage(),
                'total' => $zones->total(),
            ],
            'counts' => $counts,
        ]);
    }

    public function store(StoreServiceZoneRequest $request)
    {
        $zone = $this->zoneService->createZone($request->validated());

        return $this->successResponse(
            new ServiceZoneResource($zone),
            __('messages.service_zone_saved_successfully'),
            201
        );
    }

    public function show($id)
    {
        $zone = ServiceZone::with('services:id,name_ar,name_en')->findOrFail($id);
        return $this->successResponse(new ServiceZoneResource($zone));
    }

    public function update(UpdateServiceZoneRequest $request, $id)
    {
        $zone = ServiceZone::findOrFail($id);
        $updatedZone = $this->zoneService->updateZone($zone, $request->validated());

        return $this->successResponse(
            new ServiceZoneResource($updatedZone),
            __('messages.service_zone_updated_successfully')
        );
    }

    public function destroy($id)
    {
        $zone = ServiceZone::findOrFail($id);
        $this->zoneService->deleteZone($zone);

        return $this->successResponse(null, __('messages.service_zone_deleted_successfully'));
    }

    public function checkPoint(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'service_id' => 'nullable|exists:services,id',
        ]);

        $lat = (float) ($validated['lat'] ?? $validated['latitude']);
        $lng = (float) ($validated['lng'] ?? $validated['longitude']);

        $zone = $this->zoneService->findZoneForPoint($lat, $lng);

        if ($zone) {
            $isAvailableForService = true;
            if (!empty($validated['service_id'])) {
                $service = Service::find($validated['service_id']);
                if ($service && $service->serviceZones()->count() > 0) {
                    $isAvailableForService = $service->serviceZones()->where('service_zones.id', $zone->id)->where('status', 'active')->exists();
                }
            }

            if ($isAvailableForService) {
                return $this->successResponse([
                    'is_serviceable' => true,
                    'zone' => new ServiceZoneResource($zone),
                ]);
            }
        }

        return $this->successResponse([
            'is_serviceable' => false,
            'zone' => null,
        ], __('messages.service_zone_not_available'));
    }
}
