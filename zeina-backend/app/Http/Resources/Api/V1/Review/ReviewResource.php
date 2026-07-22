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
        $targetName = '';
        $targetType = 'service';

        if ($this->reviewable_type === \App\Models\Service::class) {
            $targetType = 'service';
            $targetName = app()->getLocale() === 'ar' ? ($this->reviewable?->name_ar ?? '') : ($this->reviewable?->name_en ?? '');
        } elseif ($this->reviewable_type === \App\Models\Worker::class) {
            $targetType = 'worker';
            $targetName = $this->reviewable?->name ?? '';
        }

        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'rating_percentage' => (float) ($this->rating_percentage ?? (($this->rating / 5) * 100)),
            'comment' => $this->comment,
            'status' => $this->status ?? 'pending',
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'first_name' => $this->customer->first_name,
                'last_name' => $this->customer->last_name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ] : null,
            'target_type' => $targetType,
            'target' => [
                'id' => $this->reviewable_id,
                'name' => $targetName,
            ],
            'booking_id' => $this->booking_id,
            'booking_ref' => $this->booking?->booking_ref,
            'action_at' => $this->action_at ? $this->action_at->toDateTimeString() : null,
            'action_by' => $this->actionBy ? [
                'id' => $this->actionBy->id,
                'name' => $this->actionBy->first_name.' '.$this->actionBy->last_name,
            ] : null,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'created_since' => $this->created_at ? $this->created_at->format('d M, Y') : null,
        ];
    }
}
