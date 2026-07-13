<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Review\ReviewResource;
use App\Models\Review;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $query = Review::with(['booking', 'customer', 'reviewable']);

        if ($request->filled('service_id')) {
            $query->where('reviewable_type', \App\Models\Service::class)
                ->where('reviewable_id', $request->input('service_id'));
        }

        if ($request->filled('worker_id')) {
            $query->where('reviewable_type', \App\Models\Worker::class)
                ->where('reviewable_id', $request->input('worker_id'));
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

        $reviews = $query->latest()->paginate($request->input('per_page', 15));

        return $this->successResponse([
            'items' => ReviewResource::collection($reviews->items()),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ]
        ], 'Reviews retrieved successfully');
    }
}
