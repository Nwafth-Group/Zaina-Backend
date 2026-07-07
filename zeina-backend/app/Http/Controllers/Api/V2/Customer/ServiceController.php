<?php

namespace App\Http\Controllers\Api\V2\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V2\Customer\ListServicesRequest;
use App\Http\Requests\Api\V2\Customer\GetAvailableWorkersRequest;
use App\Http\Requests\Api\V2\Customer\GetShiftsForDateRequest;
use App\Http\Resources\Api\V2\Service\ServiceResource;
use App\Http\Resources\Api\V2\Service\ServiceListResource;
use App\Http\Resources\Api\V1\Worker\WorkerResource;
use App\Models\Category;
use App\Models\Service;
use App\Service\Customer\CustomerService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private CustomerService $customerService) {}

    /**
     * Get all active services filtered and paginated.
     */
    public function index(ListServicesRequest $request)
    {
        $perPage = $request->input('per_page', 10);
        $services = $this->customerService->listServices($request->validated(), $perPage);

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

    /**
     * Retrieve available shifts for a date.
     */
    public function getShiftsForDate(GetShiftsForDateRequest $request, Service $service)
    {
        $shiftsData = $this->customerService->getShiftsForDate($service, $request->date);

        return $this->successResponse(['shifts' => $shiftsData], __('messages.shifts_retrieved'));
    }

    /**
     * Retrieve available preferred and other workers for a shift.
     */
    public function getAvailableWorkers(GetAvailableWorkersRequest $request, Service $service)
    {
        $workers = $this->customerService->getAvailableWorkers(
            $service,
            $request->date,
            $request->start_time
        );

        return $this->successResponse([
            'preferred_workers' => WorkerResource::collection($workers['preferred']),
        ], __('messages.providers_fetch_success') ?? 'تم جلب مقدمي الخدمة المتاحين بنجاح.');
    }

    /**
     * Check coverage for the customer's active/saved address and a specific service.
     */
    public function checkCoverage(Request $request, Service $service)
    {
        $user = auth()->user();
        
        $address = \App\Models\CustomerAddress::with('area')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

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
