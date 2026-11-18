<?php

namespace App\Http\Resources\Api\V1\Booking;

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

        $expectedArrivalTime = null;
        if ($this->service && $this->service->relationLoaded('rules') && $this->service->rules) {
            $rules = $this->service->rules;
            $buffer = (int) ($rules->arrival_buffer_minutes ?? 0);
            $grace = (int) ($rules->late_arrival_grace_minutes ?? 0);

            $startTime = Carbon::parse($this->start_time);
            $fromTime = $startTime->copy()->subMinutes($buffer)->format('g:i A');
            $toTime = $startTime->copy()->addMinutes($grace)->format('g:i A');

            $expectedArrivalTime = "{$fromTime} - {$toTime}";
        }

        $firstVisitDate = $this->booking_date;
        $firstVisitFormatted = $startDayAndDate;
        if ($this->relationLoaded('visits') && $this->visits && $this->visits->isNotEmpty()) {
            $firstVisit = $this->visits->sortBy('visit_date')->first();
            $firstVisitDate = $firstVisit->visit_date;

            $fvDate = Carbon::parse($firstVisitDate);
            $fvDayName = $fvDate->isoFormat('dddd');
            $firstVisitFormatted = "{$fvDayName}, {$fvDate->format('d M Y')}";
        }

        return [
            'booking_id' => $this->id,
            'booking_reference' => $this->booking_ref,
            'service_id' => $this->service_id,
            'service_name' => $serviceName,
            'service_date' => $this->booking_date,
            'start_time' => Carbon::parse($this->start_time)->format('H:i'),
            'end_time' => Carbon::parse($this->end_time)->format('H:i'),
            'expected_arrival_time' => $expectedArrivalTime,
            'first_schedule_date' => $firstVisitDate,
            'first_schedule_date_formatted' => $firstVisitFormatted,
            'booking_status' => $this->status,
            'visit_type' => $this->visit_type ?? 'single_visit',
            'team_id' => $this->team_id,
            'team' => $this->relationLoaded('team') && $this->team ? [
                'id' => $this->team->id,
                'code' => $this->team->code,
                'name' => $lang === 'ar' ? $this->team->name_ar : $this->team->name_en,
            ] : null,
            'selected_options' => is_array($this->selected_options)
                ? $this->selected_options
                : json_decode($this->selected_options ?? '[]', true),
            'visit_schedule' => $this->relationLoaded('visits') && $this->visits ? $this->visits->map(fn($v) => [
                'id' => $v->id,
                'visit_date' => $v->visit_date,
                'visit_day' => Carbon::parse($v->visit_date)->isoFormat('dddd'),
                'start_time' => Carbon::parse($v->start_time)->format('H:i'),
                'end_time' => Carbon::parse($v->end_time)->format('H:i'),
                'actual_start_time' => $v->actual_start_time ? Carbon::parse($v->actual_start_time)->format('H:i') : null,
                'actual_end_time' => $v->actual_end_time ? Carbon::parse($v->actual_end_time)->format('H:i') : null,
                'duration' => (int) ceil($this->duration_minutes / 60),
                'visit_status' => $v->status,
            ])->values()->toArray() : [],
            'total_amount' => (float) $this->total_amount,
            'currency_code' => $this->currency_code ?? 'EGP',
            'is_reviewed' => \App\Models\Review::where('booking_id', $this->id)
                ->where('customer_id', $this->customer_id ?? auth()->id())
                ->exists(),
            'is_reported' => \App\Models\Report::where('booking_id', $this->id)
                ->where('customer_id', $this->customer_id ?? auth()->id())
                ->exists(),

            // Pre-formatted card details
            'card_details' => [
                'contract_number_request' => $this->booking_ref,
                'start_day_and_date' => $startDayAndDate,
                'duration' => (int) ceil($this->duration_minutes / 60),
                'status' => $this->status,
                'visit_type' => $this->visit_type ?? 'single_visit',
                'selected_options' => is_array($this->selected_options)
                    ? $this->selected_options
                    : json_decode($this->selected_options ?? '[]', true),
                'is_reviewed' => \App\Models\Review::where('booking_id', $this->id)
                    ->where('customer_id', $this->customer_id ?? auth()->id())
                    ->exists(),
                'is_reported' => \App\Models\Report::where('booking_id', $this->id)
                    ->where('customer_id', $this->customer_id ?? auth()->id())
                    ->exists(),
                'price_and_currency' => [
                    'amount' => (float) $this->total_amount,
                    'currency' => $this->currency_code ?? 'EGP',
                ]
            ]
        ];
    }
}
