<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\ListServicesRequest;
use App\Http\Requests\Api\V1\Customer\GetAvailableDatesRequest;
use App\Http\Requests\Api\V1\Customer\ValidateHospitalityBookingRequest;
use App\Http\Resources\Api\V1\Service\ServiceResource;
use App\Http\Resources\Api\V1\Service\ServiceListResource;
use App\Models\Service;
use App\Service\Customer\CustomerService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private CustomerService $customerService,
        private \App\Service\Customer\CustomerBookingService $customerBookingService
    ) {}

    /**
     * Get all active services filtered.
     */
    public function index(ListServicesRequest $request)
    {
        $services = $this->customerService->listServices($request->validated());

        return $this->successResponse(
            ServiceListResource::collection($services),
            __('messages.services_retrieved')
        );
    }

    /**
     * Show detailed active service information.
     */
    public function show(Service $service)
    {
        $serviceWithDetails = $this->customerService->showService($service);

        return $this->successResponse(
            new ServiceResource($serviceWithDetails),
            __('messages.service_retrieved')
        );
    }

    public function getAvailableVisitTypes(Service $service)
    {
        $visitTypes = collect($service->available_visit_types ?? [])->map(function ($item) {
            return [
                'type'     => $item['type'] ?? '',
                'discount' => isset($item['discount']) ? (float) $item['discount'] : 0.0,
            ];
        });

        return $this->successResponse(
            $visitTypes,
            __('messages.available_visit_types_retrieved') ?? 'Available visit types retrieved successfully.'
        );
    }

    /**
     * Check coverage for the customer's active/saved address and a specific service.
     */
    public function checkCoverage(Request $request, Service $service)
    {
        $user = auth()->user();
        
        $query = \App\Models\CustomerAddress::with('area')
            ->where('user_id', $user->id)
            ->where('is_active', true);

        if ($request->filled('address_id')) {
            $address = $query->where('id', $request->address_id)->first();
        } else {
            $address = (clone $query)->where('is_default', true)->first() ?? $query->first();
        }

        if (!$address) {
            return $this->successResponse([
                'has_address' => false,
                'is_covered'  => false,
                'address'     => null,
            ], __('messages.no_active_address'));
        }

        $isCovered = $service->coverages()
            ->where('city_id', $address->area->city_id)
            ->where(function ($q) use ($address) {
                $q->where('area_id', $address->area_id)->orWhereNull('area_id');
            })
            ->where('status', 'active')
            ->exists();

        return $this->successResponse([
            'has_address' => true,
            'is_covered'  => (bool) $isCovered,
            'address'     => new \App\Http\Resources\Api\V1\Address\AddressResource($address),
        ], __('messages.coverage_check_completed'));
    }

}
