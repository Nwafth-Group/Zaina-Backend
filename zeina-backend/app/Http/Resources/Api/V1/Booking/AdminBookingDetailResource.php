<?php

namespace App\Http\Resources\Api\V1\Booking;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full booking payload for the admin details view: customer, service, schedule,
 * address, money breakdown, assigned workers and the status-change activity
 * feed. Relations are eager-loaded by the controller's `show`.
 */
class AdminBookingDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lang = app()->getLocale();
        $address = $this->whenLoaded('address') instanceof \App\Models\CustomerAddress ? $this->address : null;

        return [
            'id' => $this->id,
            'booking_ref' => $this->booking_ref,
            'status' => $this->status,
            'payment_status' => $this->payment_status,

            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => trim(($this->customer->first_name ?? '').' '.($this->customer->last_name ?? '')),
                'phone' => $this->customer->phone_number,
                'email' => $this->customer->email,
            ] : null,

            'service_name' => $this->service
                ? ($lang === 'ar' ? $this->service->name_ar : $this->service->name_en)
                : null,

            'booking_date' => $this->booking_date ? Carbon::parse($this->booking_date)->format('d M, Y') : null,
            'time_slot' => ($this->start_time && $this->end_time)
                ? Carbon::parse($this->start_time)->format('h:i A').' - '.Carbon::parse($this->end_time)->format('h:i A')
                : null,
            'duration_minutes' => $this->duration_minutes,

            'workers' => $this->relationLoaded('workers')
                ? $this->workers->map(fn ($w) => ['id' => $w->id, 'name' => $w->name, 'nationality' => $w->nationality])->values()
                : [],

            'address' => $address ? [
                'label' => $address->label,
                'line' => trim(implode(', ', array_filter([
                    $address->street,
                    $address->building_name,
                    $address->building_no ? "Bld {$address->building_no}" : null,
                    $address->floor ? "Floor {$address->floor}" : null,
                    $address->apartment_no ? "Apt {$address->apartment_no}" : null,
                ]))),
                'phone' => $address->phone_number,
            ] : null,

            'price_summary' => [
                'subtotal' => (float) $this->price_subtotal,
                'tax' => (float) $this->tax_amount,
                'discount' => (float) $this->discount_amount,
                'total' => (float) $this->total_amount,
                'currency' => 'EGP',
            ],

            'customer_notes' => $this->customer_notes,

            'activity' => $this->relationLoaded('histories')
                ? $this->histories->map(fn ($h) => [
                    'id' => $h->id,
                    'old_status' => $h->old_status,
                    'new_status' => $h->new_status,
                    'note' => $h->note,
                    'by' => $h->createdBy
                        ? trim(($h->createdBy->first_name ?? '').' '.($h->createdBy->last_name ?? ''))
                        : null,
                    'at' => $h->created_at?->toDateTimeString(),
                ])->values()
                : [],

            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
