<?php

namespace App\Http\Resources\Api\V1\Review;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single rating/review left for a reviewable (worker or service) — used by the
 * worker/service details modals. Expects `customer` and `booking` to be
 * eager-loaded by the controller.
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'customer' => [
                'id' => $this->customer->id,
                'first_name' => $this->customer->first_name,
                'last_name' => $this->customer->last_name,
            ],
            'booking_ref' => $this->booking?->booking_ref,
            'created_since' => $this->created_at?->format('d M, Y'),
        ];
    }
}
