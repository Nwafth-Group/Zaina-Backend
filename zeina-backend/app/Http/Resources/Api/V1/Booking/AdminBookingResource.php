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
                ? trim(($this->customer->first_name ?? '') . ' ' . ($this->customer->last_name ?? ''))
                : null,
            'customer_phone' => $this->customer?->phone_number,
            'service_name' => $this->service
                ? ($lang === 'ar' ? $this->service->name_ar : $this->service->name_en)
                : null,
            'booking_date' => $this->booking_date ? Carbon::parse($this->booking_date)->format('d M, Y') : null,
            'time_slot' => ($this->start_time && $this->end_time)
                ? Carbon::parse($this->start_time)->format('h:i A') . ' - ' . Carbon::parse($this->end_time)->format('h:i A')
                : null,
            'workers_count' => $this->relationLoaded('workers') ? $this->workers->count() : 0,
            'worker_name' => $this->relationLoaded('workers') && $this->workers->count() > 0
                ? $this->workers->map(fn($w) => $w->name ?? trim(($w->first_name ?? '') . ' ' . ($w->last_name ?? '')))->filter()->join(', ')
                : null,
            'workers' => $this->relationLoaded('workers') ? $this->workers->map(fn($w) => [
                'id' => $w->id,
                'name' => $w->name ?? trim(($w->first_name ?? '') . ' ' . ($w->last_name ?? '')),
            ]) : [],
            'number_of_workers' => (int) ($this->number_of_workers ?? ($this->relationLoaded('workers') ? $this->workers->count() : 0)),
            'worker_gender' => $this->worker_gender,
            'team_id' => $this->team_id,
            'team' => $this->team ? [
                'id' => $this->team->id,
                'code' => $this->team->code,
                'name' => $lang === 'ar' ? $this->team->name_ar : $this->team->name_en,
            ] : null,
            'selected_options' => is_array($this->selected_options)
                ? $this->selected_options
                : json_decode($this->selected_options ?? '[]', true),
            'assignment_status' => ($this->relationLoaded('workers') && $this->workers->count() >= (int) ($this->number_of_workers ?? 1)) ? 'assigned' : 'pending_assignment',
            'visit_type' => $this->visit_type ?? 'single_visit',
            'pricing_type' => $this->service?->pricing_type ?? 'per_service',
            'number_of_children' => $this->number_of_children ? (int) $this->number_of_children : null,
            'number_of_persons' => $this->number_of_persons ? (int) $this->number_of_persons : null,
            'is_reviewed' => \App\Models\Review::where('booking_id', $this->id)->exists(),
            'is_reported' => \App\Models\Report::where('booking_id', $this->id)->exists(),
            'total_amount' => (float) $this->total_amount,
            'currency' => $this->currency_code ?? 'EGP',
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
