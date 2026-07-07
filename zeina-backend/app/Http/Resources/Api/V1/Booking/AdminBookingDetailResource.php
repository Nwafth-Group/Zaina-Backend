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

        $pricePerPeriod = (float) ($this->service->base_price ?? 0);
        $visitsCount = (int) ($this->visit_number ?? 1);
        $workersCount = $this->relationLoaded('workers') && $this->workers ? $this->workers->count() : 1;
        $subtotalAmountVal = $pricePerPeriod * $visitsCount * $workersCount;

        $discountPercent = 0;
        if ($this->service) {
            $availableTypes = $this->service->available_visit_types ?? [];
            $visitTypeVal = $this->visit_type ?? 'single_visit';
            foreach ($availableTypes as $vt) {
                $vtType = $vt['type'] ?? '';
                if ($vtType === $visitTypeVal 
                    || ($visitTypeVal === 'three_visits_per_week' && ($vtType === '3_days_per_week' || $vtType === '3_visits_per_week'))
                    || ($visitTypeVal === '3_days_per_week' && ($vtType === 'three_visits_per_week' || $vtType === '3_visits_per_week'))
                ) {
                    $discountPercent = (float) ($vt['discount'] ?? 0);
                    break;
                }
            }
        }
        $serviceDiscountAmount = $subtotalAmountVal * ($discountPercent / 100);
        $couponDiscountAmount = max(0.0, ((float) $this->discount_amount) - $serviceDiscountAmount);

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
            'visit_type' => $this->visit_type ?? 'single_visit',
            'visit_number' => (int) ($this->visit_number ?? 1),
            'visit_schedule' => $this->relationLoaded('visits') && $this->visits ? $this->visits->map(fn($v) => [
                'visit_date'   => $v->visit_date,
                'visit_day'    => Carbon::parse($v->visit_date)->format('l'),
                'start_time'   => Carbon::parse($v->start_time)->format('H:i'),
                'end_time'     => Carbon::parse($v->end_time)->format('H:i'),
                'duration'     => (int) ceil($v->duration_minutes / 60),
                'visit_status' => $v->status,
            ])->values()->toArray() : [],
            'assigned_worker_ids' => $this->relationLoaded('workers') ? $this->workers->pluck('id')->toArray() : [],

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
                'subtotal'                => (float) $this->price_subtotal,
                'tax'                     => (float) $this->tax_amount,
                'service_discount_amount' => (float) $serviceDiscountAmount,
                'coupon_discount_amount'  => (float) $couponDiscountAmount,
                'has_coupon'              => !is_null($this->coupon_id),
                'coupon_id'               => $this->coupon_id,
                'coupon_code'             => $this->relationLoaded('coupon') && $this->coupon ? $this->coupon->code : null,
                'discount'                => (float) $this->discount_amount,
                'total'                   => (float) $this->total_amount,
                'currency'                => $this->currency_code ?? 'EGP',
                'payment_transaction_id'  => $this->payment_transaction_id ?? '',
                'payment_method'          => $this->payment_method,
            ],

            'contract_id' => $this->contract_id ?? 1,
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
