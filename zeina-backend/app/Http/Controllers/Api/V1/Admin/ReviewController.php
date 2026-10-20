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
        $query = Review::with(['booking', 'customer', 'reviewable', 'actionBy']);

        // Summary counts for tabs
        $counts = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
        ];

        // 1. Status Filter
        if ($request->filled('status') && in_array($request->input('status'), ['pending', 'approved', 'rejected'])) {
            $query->where('status', $request->input('status'));
        }

        // 2. Type Filter (service / worker)
        if ($request->filled('type')) {
            $type = $request->input('type');
            if ($type === 'service') {
                $query->where('reviewable_type', \App\Models\Service::class);
            } elseif ($type === 'worker') {
                $query->where('reviewable_type', \App\Models\Worker::class);
            }
        }

        // 3. Search Filter (customer name, email, comment, booking_ref)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($userQ) use ($search) {
                        $userQ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('booking', function ($bookQ) use ($search) {
                        $bookQ->where('booking_ref', 'like', "%{$search}%");
                    });
            });
        }

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

        $perPage = (int) $request->input('per_page', 15);
        $reviews = $query->latest()->paginate($perPage);

        return $this->successResponse([
            'items' => ReviewResource::collection($reviews->items()),
            'counts' => $counts,
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ], __('messages.reviews_retrieved') ?? 'Reviews retrieved successfully.');
    }

    /**
     * Approve a pending or rejected review.
     */
    public function approve(Review $review)
    {
        $review->update([
            'status' => 'approved',
            'action_by' => auth()->id(),
            'action_at' => now(),
        ]);

        \App\Service\BookingService::updateAverageRating($review->reviewable_type, $review->reviewable_id);

        $review->load(['booking', 'customer', 'reviewable', 'actionBy']);

        if ($review->customer) {
            try {
                $review->customer->notify(new \App\Notifications\ReviewStatusNotification($review, 'approved'));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to notify customer on review approval: ' . $e->getMessage());
            }
        }

        return $this->successResponse(
            new ReviewResource($review),
            __('messages.review_approved_successfully') ?? 'Review approved successfully.'
        );
    }

    /**
     * Reject a pending or approved review.
     */
    public function reject(Request $request, Review $review)
    {
        $review->update([
            'status' => 'rejected',
            'action_by' => auth()->id(),
            'action_at' => now(),
        ]);

        \App\Service\BookingService::updateAverageRating($review->reviewable_type, $review->reviewable_id);

        $review->load(['booking', 'customer', 'reviewable', 'actionBy']);

        $reason = $request->input('reason');
        if ($review->customer) {
            try {
                $review->customer->notify(new \App\Notifications\ReviewStatusNotification($review, 'rejected', $reason));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to notify customer on review rejection: ' . $e->getMessage());
            }
        }

        return $this->successResponse(
            new ReviewResource($review),
            __('messages.review_rejected_successfully') ?? 'Review rejected successfully.'
        );
    }
}
