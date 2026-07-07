<?php

namespace App\Http\Resources\Api\V2\Booking;

use App\Http\Resources\Api\V1\Address\AddressResource;
use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Http\Resources\Api\V1\Worker\WorkerResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $this->load(['service.media', 'service.rules', 'coupon', 'address', 'workers']);
        
        $lang = app()->getLocale();
        $serviceName = $this->service ? ($lang == 'ar' ? $this->service->name_ar : $this->service->name_en) : '';

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

        // Worker representation: nationality and names list
        $workersData = [];
        if ($this->relationLoaded('workers') && $this->workers) {
            foreach ($this->workers as $worker) {
                $workersData[] = [
                    'id'          => $worker->id,
                    'name'        => $worker->name,
                    'nationality' => $worker->nationality,
                ];
            }
        }

        // Start day and date format (e.g. Monday, 07 Jul 2026)
        $startDate = Carbon::parse($this->booking_date);
        $dayName = $startDate->isoFormat('dddd');
        $startDayAndDate = "{$dayName}, {$startDate->format('d M Y')}";

        // Get service icon
        $media = $this->service ? $this->service->media->where('collection', 'service/icon')->first() : null;

        // Calculate hours duration text
        $hours = floor($this->duration_minutes / 60);
        $durationText = $hours > 0 ? "{$hours} Hours" : "{$this->duration_minutes} Mins";

        // Format address string
        $addressStr = '';
        if ($this->relationLoaded('address') && $this->address) {
            $addr = $this->address;
            $parts = array_filter([
                $addr->label,
                $addr->street,
                $addr->building_name,
                $addr->building_no ? "Bldg {$addr->building_no}" : null,
                $addr->floor ? "Floor {$addr->floor}" : null,
                $addr->apartment_no ? "Apt {$addr->apartment_no}" : null,
            ]);
            $addressStr = implode(', ', $parts);
        }

        return [
            // Standard V2 model fields (from US-6 spec)
            'booking_id'             => $this->id,
            'booking_reference'      => $this->booking_ref,
            'customer_id'            => $this->customer_id,
            'service_id'             => $this->service_id,
            'service_name'           => $serviceName,
            // 'service'                => new \App\Http\Resources\Api\V2\Service\ServiceResource($this->whenLoaded('service')),
            'visit_type'             => $this->visit_type ?? 'single_visit',
            'visit_number'           => (int) ($this->visit_number ?? 1),
            'visit_schedule'         => $this->relationLoaded('visits') && $this->visits ? $this->visits->map(fn($v) => [
                'visit_date'   => $v->visit_date,
                'visit_day'    => Carbon::parse($v->visit_date)->isoFormat('dddd'),
                'start_time'   => Carbon::parse($v->start_time)->format('H:i'),
                'end_time'     => Carbon::parse($v->end_time)->format('H:i'),
                'duration'     => (int) ceil($this->duration_minutes / 60),
                'visit_status' => $v->status,
            ])->values()->toArray() : [],
            'address_id'             => $this->address_id,
            'service_date'           => $this->booking_date,
            'start_time'             => Carbon::parse($this->start_time)->format('H:i'),
            'end_time'               => Carbon::parse($this->end_time)->format('H:i'),
            'duration'               => (int) ceil($this->duration_minutes / 60),
            'number_of_workers'      => $this->relationLoaded('workers') && $this->workers ? $this->workers->count() : 1,
            'assigned_worker_ids'    => $this->relationLoaded('workers') && $this->workers ? $this->workers->pluck('id')->toArray() : [],
            'payment_transaction_id' => $this->payment_transaction_id ?? '',
            'payment_status'         => $this->payment_status,
            'total_amount'           => (float) $this->total_amount,
            'currency_code'          => $this->currency_code ?? 'EGP',
            'contract_id'            => $this->contract_id ?? 1,
            'booking_status'         => $this->status,
            'created_at'             => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at'             => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Formatting/UI fields
            'image'                  => $media ? (new MediaResource($media))->getFileUrl() : null,
            'date_day'               => $startDate->format('d'),
            'date_month'             => $startDate->format('M'),
            'full_date'              => $startDate->format('d M, Y'),
            'time_slot'              => Carbon::parse($this->start_time)->format('h:i A') . ' - ' . Carbon::parse($this->end_time)->format('h:i A'),

            // V2 detailed service config rules
            'service_rules' => $this->service && $this->service->rules ? [
                'allow_cancel'               => (bool) $this->service->rules->allow_cancel,
                'free_cancel_cutoff_hours'   => (int) $this->service->rules->free_cancel_cutoff_hours,
                'allow_reschedule'           => (bool) $this->service->rules->allow_reschedule,
                'reschedule_cutoff_hours'    => (int) $this->service->rules->reschedule_cutoff_hours,
                'arrival_buffer_minutes'     => (int) $this->service->rules->arrival_buffer_minutes,
                'late_arrival_grace_minutes' => (int) $this->service->rules->late_arrival_grace_minutes,
                'allow_same_day'             => (bool) $this->service->rules->allow_same_day,
                'max_advance_booking_days'   => (int) $this->service->rules->max_advance_booking_days,
            ] : null,

            // V1 Compatibility structured objects
            'address' => new AddressResource($this->whenLoaded('address')),
            'workers' => WorkerResource::collection($this->whenLoaded('workers')),
            
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
            ],

            'important_notes' => $this->whenLoaded('service', function () use ($lang) {
                $rules = $this->service->rules;
                if (!$rules) return [];

                $notes = [];

                if ((bool) ($rules->allow_reschedule ?? true)) {
                    $notes[] = $lang == 'ar' 
                        ? 'يمكن إعادة الجدولة قبل ' . $rules->reschedule_cutoff_hours . ' ساعات' 
                        : 'Reschedule Can Be Before ' . $rules->reschedule_cutoff_hours . ' Hours';
                } else {
                    $notes[] = $lang == 'ar' 
                        ? 'إعادة الجدولة غير مسموح بها لهذه الخدمة' 
                        : 'Rescheduling is not allowed for this service';
                }

                if ((bool) ($rules->allow_cancel ?? true)) {
                    $notes[] = $lang == 'ar' 
                        ? 'الإلغاء المجاني متاح قبل ' . $rules->free_cancel_cutoff_hours . ' ساعات' 
                        : 'Free Cancellation Before ' . $rules->free_cancel_cutoff_hours . ' Hours';
                } else {
                    $notes[] = $lang == 'ar' 
                        ? 'الإلغاء غير مسموح به لهذه الخدمة' 
                        : 'Cancellation is not allowed for this service';
                }

                if (isset($rules->arrival_buffer_minutes) || isset($rules->late_arrival_grace_minutes)) {
                    $buffer = (int) ($rules->arrival_buffer_minutes ?? 0);
                    $grace = (int) ($rules->late_arrival_grace_minutes ?? 0);

                    $startTime = Carbon::parse($this->start_time);
                    $fromTime = $startTime->copy()->subMinutes($buffer)->format('h:i A');
                    $toTime = $startTime->copy()->addMinutes($grace)->format('h:i A');

                    $notes[] = $lang == 'ar' 
                        ? 'وقت الوصول المتوقع هو حوالي ' . $fromTime . ' - ' . $toTime 
                        : 'Expected Arrival Time is about ' . $fromTime . ' - ' . $toTime;
                }

                return $notes;
            }),

            // Card item format fields for easy listing rendering
            'card_details' => [
                'contract_number_request' => $this->booking_ref,
                'start_day_and_date'      => $startDayAndDate,
                'duration'                => (int) ceil($this->duration_minutes / 60),
                'workers'                 => $workersData,
                'address'                 => $addressStr,
                'status'                  => $this->status,
                'visit_type'              => $this->visit_type ?? 'single_visit',
                'price_and_currency'      => [
                    'amount'   => (float) $this->total_amount,
                    'currency' => $this->currency_code ?? 'EGP',
                ]
            ]
        ];
    }
}
