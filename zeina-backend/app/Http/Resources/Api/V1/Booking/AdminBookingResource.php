<?php

namespace App\Http\Resources\Api\V1\Booking;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lean booking shape for the admin listing table — customer, service, schedule,
 * money and the two status flags. Relations are expected to be eager-loaded by
 * the controller (customer, service, workers) to avoid N+1 queries.
 */
class AdminBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lang = app()->getLocale();

        return [
            'id' => $this->id,
            'booking_ref' => $this->booking_ref,
            'customer_name' => $this->customer
                ? trim(($this->customer->first_name ?? '').' '.($this->customer->last_name ?? ''))
                : null,
            'customer_phone' => $this->customer?->phone_number,
            'service_name' => $this->service
                ? ($lang === 'ar' ? $this->service->name_ar : $this->service->name_en)
                : null,
            'booking_date' => $this->booking_date ? Carbon::parse($this->booking_date)->format('d M, Y') : null,
            'time_slot' => ($this->start_time && $this->end_time)
                ? Carbon::parse($this->start_time)->format('h:i A').' - '.Carbon::parse($this->end_time)->format('h:i A')
                : null,
            'workers_count' => $this->relationLoaded('workers') ? $this->workers->count() : 0,
            'total_amount' => (float) $this->total_amount,
            'currency' => 'EGP',
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
