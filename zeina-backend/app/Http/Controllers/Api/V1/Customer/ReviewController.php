<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Review\ReviewResource;
use App\Models\Service;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request, Service $service)
    {
        $reviews = \App\Models\Review::where('reviewable_type', Service::class)
            ->where('reviewable_id', $service->id)
            ->where('status', 'approved')
            ->latest()
            ->get();

        return $this->successResponse(ReviewResource::collection($reviews), __('messages.reviews_retrieved') ?? 'Reviews retrieved successfully');
    }
}
