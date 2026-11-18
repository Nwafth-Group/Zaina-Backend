<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Filters\Worker\MinRatingFilter;
use App\Filters\Worker\NationalityFilter;
use App\Filters\Worker\SearchFilter;
use App\Filters\Worker\ServiceFilter;
use App\Filters\Worker\StatusFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreWorkerRequest;
use App\Http\Requests\Api\V1\Admin\UpdateWorkerRequest;
use App\Http\Resources\Api\V1\Booking\AdminBookingResource;
use App\Http\Resources\Api\V1\Review\ReviewResource;
use App\Http\Resources\Api\V1\Worker\WorkerResource;
use App\Http\Resources\Api\V1\Worker\WorkerListResource;
use App\Models\Worker;
use App\Service\MediaService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;

class WorkerController extends Controller
{
    use ApiResponseTrait;

    public function __construct(public MediaService $mediaService) {}

    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $countsQuery = app(Pipeline::class)
            ->send(Worker::query())
            ->through([
                SearchFilter::class,
                NationalityFilter::class,
                ServiceFilter::class,
                MinRatingFilter::class,
            ])
            ->thenReturn();

        $counts = [
            'all' => (clone $countsQuery)->count(),
            'active' => (clone $countsQuery)->where('status', 'active')->count(),
            'inactive' => (clone $countsQuery)->where('status', 'inactive')->count(),
            'female' => (clone $countsQuery)->whereRaw('LOWER(gender) = ?', ['female'])->count(),
        ];

        // 1. تمرير الاستعلام عبر سلسلة الفلاتر (Pipeline Pattern)
        $query = app(Pipeline::class)
            ->send(Worker::query()->with(['media', 'services', 'team']))
            ->through([
                SearchFilter::class,
                StatusFilter::class,
                NationalityFilter::class,
                ServiceFilter::class,
                MinRatingFilter::class,
            ])
            ->thenReturn();

        if ($request->filled('gender')) {
            $query->whereRaw('LOWER(gender) = ?', [strtolower($request->input('gender'))]);
        }

        if ($request->boolean('no_team_only')) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('team_id');
                if ($request->filled('include_team_id')) {
                    $q->orWhere('team_id', $request->input('include_team_id'));
                }
            });
        }

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->input('team_id'));
        }

        if ($request->boolean('unassigned_only')) {
            $query->where(function ($q) use ($request) {
                $q->whereDoesntHave('services');
                if ($request->filled('include_service_id')) {
                    $q->orWhereHas('services', function ($sq) use ($request) {
                        $sq->where('services.id', $request->include_service_id);
                    });
                }
            });
        }

        // 2. الترتيب الديناميكي الآمن (allow-list)
        $allowedSorts = ['name', 'avg_rating', 'created_at', 'id'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'created_at';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        // 3. التقسيم مع الحفاظ على الفلاتر في روابط الصفحات
        $perPage = (int) $request->input('per_page', 10);
        $workers = $query->paginate($perPage)->withQueryString();

        return $this->successResponse([
            'items' => WorkerListResource::collection($workers),
            'counts' => $counts,
            'meta' => [
                'current_page' => $workers->currentPage(),
                'last_page' => $workers->lastPage(),
                'per_page' => $workers->perPage(),
                'total' => $workers->total(),
            ],
        ], __('messages.workers_retrieved_successfully'));
    }

    /**
     * Distinct nationalities to populate the listing filter dropdown.
     */
    public function nationalities()
    {
        $nationalities = Worker::query()
            ->whereNotNull('nationality')
            ->distinct()
            ->orderBy('nationality')
            ->pluck('nationality');

        return $this->successResponse($nationalities, __('messages.workers_retrieved_successfully'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWorkerRequest $request)
    {
        $data = $request->validated();
        $create_data = collect($data)->only(['name', 'nationality', 'status', 'phone_number', 'id_number', 'address', 'gender', 'team_id', 'work_days'])->toArray();
        if (!isset($create_data['work_days']) || empty($create_data['work_days'])) {
            $create_data['work_days'] = Worker::ALL_DAYS;
        }
        $worker = Worker::create($create_data);

        if (isset($data['services'])) {
            $worker->services()->sync($data['services']);
        }
        if ($request->hasFile('image')) {
            $media = $this->mediaService->upload($request->file('image'), $worker, 'worker/image', 'public');
        }

        $worker->load(['services', 'media', 'team']);

        return $this->successResponse(new WorkerResource($worker), __('messages.worker_created_successfully'), 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(Worker $worker)
    {
        $worker->load(['services', 'media', 'team']);

        return $this->successResponse(new WorkerResource($worker), __('messages.worker_retrieved_successfully'));
    }

    /**
     * Rich profile for the worker details modal: the worker, headline stats,
     * the latest bookings they were assigned to, and their ratings/reviews.
     */
    public function details(Worker $worker)
    {
        $worker->load(['services', 'media', 'team']);

        $latestBookings = $worker->bookings()
            ->with(['customer', 'service'])
            ->orderByDesc('bookings.created_at')
            ->limit(10)
            ->get();

        $reviews = $worker->reviews()
            ->with(['customer', 'booking'])
            ->latest()
            ->limit(10)
            ->get();

        $stats = [
            'total_bookings' => $worker->bookings()->count(),
            'completed_bookings' => $worker->bookings()->where('bookings.status', 'completed')->count(),
            'reviews_count' => $worker->reviews()->count(),
            'avg_rating' => round((float) $worker->reviews()->avg('rating'), 2),
        ];

        return $this->successResponse([
            'worker' => new WorkerResource($worker),
            'stats' => $stats,
            'latest_bookings' => AdminBookingResource::collection($latestBookings),
            'reviews' => ReviewResource::collection($reviews),
        ], __('messages.worker_retrieved_successfully'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWorkerRequest $request, Worker $worker)
    {
        $data = $request->validated();
        $update_data = collect($data)->only(['name', 'nationality', 'status', 'phone_number', 'id_number', 'address', 'gender', 'team_id', 'work_days'])->toArray();
        $worker->update($update_data);
        if (isset($data['services'])) {
            $worker->services()->sync($data['services']);
        }
        if ($request->hasFile('image')) {
            if ($worker->media()->where('collection', 'worker/image')->exists()) {
                $oldMedia = $worker->media()->where('collection', 'worker/image')->first();
                $this->mediaService->delete($oldMedia);
            }
            $media = $this->mediaService->upload($request->file('image'), $worker, 'worker/image', 'public');
        }

        $worker->load(['services', 'media', 'team']);

        return $this->successResponse(new WorkerResource($worker), __('messages.worker_updated_successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Worker $worker)
    {
        // Check for relationships
        $hasBookings = \App\Models\Booking::where('worker_id', $worker->id)->exists();
        $hasAssignments = \App\Models\BookingAssignment::where('worker_id', $worker->id)->exists();
        $hasReports = \App\Models\Report::where('worker_id', $worker->id)->exists();
        $hasReviews = $worker->reviews()->exists();

        if ($hasBookings || $hasAssignments || $hasReports || $hasReviews) {
            return $this->errorResponse(
                app()->getLocale() === 'ar' 
                    ? 'لا يمكن حذف العامل لوجود سجلات مرتبطة به في النظام (حجوزات، تعيينات، بلاغات، أو تقييمات).' 
                    : 'Cannot delete the worker because there are associated records (bookings, assignments, reports, or reviews) in the system.',
                400
            );
        }

        // Delete pivot references
        $worker->services()->detach();
        // Delete media
        $worker->media()->delete();
        // Delete worker
        $worker->delete();

        return $this->successResponse(null, app()->getLocale() === 'ar' ? 'تم حذف العامل بنجاح.' : 'Worker deleted successfully.');
    }

    public function changeStatus(Worker $worker)
    {
        $newStatus = $worker->status === 'active' ? 'inactive' : 'active';

        if ($newStatus === 'inactive' || $newStatus === 'suspended') {
            if ($worker->hasUpcomingOrInProgressBookings()) {
                $count = $worker->activeBookingsCount();
                $isAr = app()->getLocale() === 'ar';

                return $this->errorResponse(
                    $isAr
                        ? "لا يمكن تغيير حالة العامل إلى غير نشط لوجود {$count} خدمة/حجز قيد التنفيذ أو قادم مسند إليه."
                        : "Cannot deactivate this worker while they have {$count} upcoming or in-progress booking(s) assigned to them.",
                    422
                );
            }
        }

        $worker->status = $newStatus;
        $worker->save();

        return $this->successResponse(new WorkerResource($worker), __('messages.worker_toggle_success', ['status' => $worker->status]));
    }
}
