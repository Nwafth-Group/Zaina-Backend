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
        $targetNameAr = '';
        $targetNameEn = '';
        $targetType = 'service';

        if ($this->reviewable_type === \App\Models\Service::class) {
            $targetType = 'service';
            $targetNameAr = $this->reviewable?->name_ar ?? '';
            $targetNameEn = $this->reviewable?->name_en ?? '';
            $targetName = app()->getLocale() === 'ar' ? ($targetNameAr ?: $targetNameEn) : ($targetNameEn ?: $targetNameAr);
        } elseif ($this->reviewable_type === \App\Models\Worker::class) {
            $targetType = 'worker';
            $targetName = $this->reviewable?->name ?? '';
            $targetNameAr = $targetName;
            $targetNameEn = $targetName;
        }

        $customerName = trim(($this->customer?->first_name ?? '') . ' ' . ($this->customer?->last_name ?? ''));
        if (empty($customerName)) {
            $customerName = $this->customer?->full_name ?: ($this->customer?->phone_number ?: 'عميل');
        }

        $phone = $this->customer?->phone_number ?? $this->customer?->phone ?? null;

        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'rating_percentage' => (float) ($this->rating_percentage ?? (($this->rating / 5) * 100)),
            'comment' => $this->comment,
            'status' => $this->status ?? 'pending',
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $customerName,
                'first_name' => $this->customer->first_name,
                'last_name' => $this->customer->last_name,
                'email' => $this->customer->email,
                'phone' => $phone,
                'mobile' => $phone,
            ] : null,
            'customer_name' => $this->customer ? $customerName : null,
            'target_type' => $targetType,
            'target' => [
                'id' => $this->reviewable_id,
                'name' => $targetName,
                'name_ar' => $targetNameAr,
                'name_en' => $targetNameEn,
            ],
            'booking_id' => $this->booking_id,
            'booking_ref' => $this->booking?->booking_ref,
            'action_at' => $this->action_at ? $this->action_at->toDateTimeString() : null,
            'action_by' => $this->actionBy ? [
                'id' => $this->actionBy->id,
                'name' => trim(($this->actionBy->first_name ?? '') . ' ' . ($this->actionBy->last_name ?? '')) ?: ($this->actionBy->full_name ?? 'Admin'),
            ] : null,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'created_since' => $this->created_at ? $this->created_at->format('d M, Y') : null,
        ];
    }
}
