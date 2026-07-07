<?php

namespace App\Http\Resources\Api\V2\Booking;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $lang = app()->getLocale();
        $serviceName = $this->service ? ($lang == 'ar' ? $this->service->name_ar : $this->service->name_en) : '';

        // Format day and date for mobile views
        $startDate = Carbon::parse($this->booking_date);
        $dayName = $startDate->isoFormat('dddd');
        $startDayAndDate = "{$dayName}, {$startDate->format('d M Y')}";

        return [
            'booking_id'        => $this->id,
            'booking_reference' => $this->booking_ref,
            'service_name'      => $serviceName,
            'service_date'      => $this->booking_date,
            'start_time'        => Carbon::parse($this->start_time)->format('H:i'),
            'end_time'          => Carbon::parse($this->end_time)->format('H:i'),
            'booking_status'    => $this->status,
            'visit_type'        => $this->visit_type ?? 'single_visit',
            'visit_schedule'    => $this->relationLoaded('visits') && $this->visits ? $this->visits->map(fn($v) => [
                'visit_date'   => $v->visit_date,
                'visit_day'    => Carbon::parse($v->visit_date)->isoFormat('dddd'),
                'start_time'   => Carbon::parse($v->start_time)->format('H:i'),
                'end_time'     => Carbon::parse($v->end_time)->format('H:i'),
                'duration'     => (int) ceil($this->duration_minutes / 60),
                'visit_status' => $v->status,
            ])->values()->toArray() : [],
            'total_amount'      => (float) $this->total_amount,
            'currency_code'     => $this->currency_code ?? 'EGP',

            // Pre-formatted card details
            'card_details' => [
                'contract_number_request' => $this->booking_ref,
                'start_day_and_date'      => $startDayAndDate,
                'duration'                => (int) ceil($this->duration_minutes / 60),
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
