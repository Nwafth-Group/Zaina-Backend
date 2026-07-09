<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Filters\Service\CategoryFilter;
use App\Filters\Service\MinRatingFilter;
use App\Filters\Service\SearchFilter;
use App\Filters\Service\StatusFilter;
use App\Filters\Service\TypeFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreServiceRequest;
use App\Http\Requests\Api\V1\Admin\UpdateServiceRequest;
use App\Http\Resources\Api\V1\Booking\AdminBookingResource;
use App\Http\Resources\Api\V1\Review\ReviewResource;
use App\Http\Resources\Api\V1\Service\ServiceResource;
use App\Http\Resources\Api\V1\Service\ServiceListResource;
use App\Models\Service;
use App\Service\ServiceWriter;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;

class ServiceController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private ServiceWriter $serviceWriter) {}

    public function index(Request $request)
    {
        // Filter the listing through the Pipeline (one class per filter).
        $query = app(Pipeline::class)
            ->send(Service::query()->with(['category', 'media', 'startingPrice']))
            ->through([
                SearchFilter::class,
                StatusFilter::class,
                TypeFilter::class,
                CategoryFilter::class,
                MinRatingFilter::class,
            ])
            ->thenReturn();

        // Safe dynamic sorting (allow-list).
        $allowedSorts = ['name_ar', 'name_en', 'avg_rating', 'created_at', 'id'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'created_at';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $this->successResponse(ServiceResource::collection($query->get()), __('messages.services_retrieved_successfully'));
    }

    public function show($id)
    {
        $service = Service::with(['category', 'duration', 'rules', 'coverages', 'coverages.city', 'coverages.area', 'priceRules', 'priceRules.city', 'priceRules.area', 'workers', 'schedules'])->findOrFail($id);

        return $this->successResponse(new ServiceResource($service), __('messages.service_retrieved_successfully'));
    }

    /**
     * Rich profile for the service details modal: the service, headline stats,
     * its latest bookings, and its ratings/reviews.
     */
    public function details(Service $service)
    {
        $service->load(['category', 'workers']);

        $latestBookings = $service->bookings()
            ->with(['customer', 'service'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $reviews = $service->reviews()
            ->with(['customer', 'booking'])
            ->latest()
            ->limit(10)
            ->get();

        $stats = [
            'total_bookings' => $service->bookings()->count(),
            'completed_bookings' => $service->bookings()->where('status', 'completed')->count(),
            'reviews_count' => $service->reviews()->count(),
            'avg_rating' => round((float) $service->reviews()->avg('rating'), 2),
        ];

        return $this->successResponse([
            'service' => new ServiceResource($service),
            'stats' => $stats,
            'latest_bookings' => AdminBookingResource::collection($latestBookings),
            'reviews' => ReviewResource::collection($reviews),
        ], __('messages.service_retrieved_successfully'));
    }

    public function store(StoreServiceRequest $request)
    {
        $service = $this->serviceWriter->create($request->validated(), $request->file('image'));

        return $this->successResponse(new ServiceResource($service), __('messages.service_created_successfully'), 201);
    }

    public function update(UpdateServiceRequest $request, Service $service)
    {
        $service = $this->serviceWriter->update($service, $request->validated(), $request->file('image'));

        return $this->successResponse(new ServiceResource($service), __('messages.service_updated_successfully'));
    }

    // دالة لتغيير حالة الخدمة (Deactivate safely)
    public function toggleStatus(Service $service)
    {
        $service->status = $service->status === 'active' ? 'inactive' : 'active';
        $service->save();

        return $this->successResponse(null, __('messages.service_toggled_successfully', ['status' => $service->status]));
    }
}
