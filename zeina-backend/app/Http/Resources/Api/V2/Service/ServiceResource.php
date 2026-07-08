<?php

namespace App\Http\Resources\Api\V2\Service;

use App\Http\Resources\Api\V1\Category\CategoryResourse;
use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Http\Resources\Api\V1\Worker\WorkerResource;
use App\Http\Resources\Api\V1\Service\ServiceDurationResource;
use App\Http\Resources\Api\V1\Service\ServiceRuleResource;
use App\Http\Resources\Api\V1\Service\ServiceCoverageResource;
use App\Http\Resources\Api\V1\Service\ServicePriceResource;
use App\Http\Resources\Api\V1\Service\ServiceScheduleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $media = $this->media->where('collection', 'service/icon')->first();
        $priceObj = $this->getCustomerPrice();
        $isStartingPrice = $priceObj ? $priceObj->is_fallback : true;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'service_code' => $this->service_code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'features' => $this->features ?? [],
            'avg_rating' => $this->avg_rating,
            
            // New Configuration Parameters
            'available_visit_types' => $this->available_visit_types ?? [],
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'default_duration' => $this->default_duration,
            'has_duration_range' => (bool) $this->has_duration_range,
            'min_duration' => $this->min_duration,
            'max_duration' => $this->max_duration,
            'min_workers' => $this->min_workers,
            'max_workers' => $this->max_workers,
            'base_price' => $this->base_price,

            'duration' => ServiceDurationResource::make($this->whenLoaded('duration')),
            'rules' => ServiceRuleResource::make($this->whenLoaded('rules')),
            'coverages' => ServiceCoverageResource::collection($this->whenLoaded('coverages')),
            'prices' => ServicePriceResource::collection($this->whenLoaded('priceRules')),
            'image' => $media ? MediaResource::make($media)->getFileUrl() : null,
 
            'preferred_workers' => $this->relationLoaded('preferred_workers') 
                ? WorkerResource::collection($this->preferred_workers)
                : WorkerResource::collection($this->getPreferredWorkers()),
            'available_dates'   => $this->getAvailableDates(),
            'available_days' => $this->relationLoaded('schedules') && $this->schedules 
                ? $this->schedules->where('is_active', true)->pluck('day')->unique()->values()->toArray()
                : [],
            'available_shifts' => $this->relationLoaded('schedules') && $this->schedules 
                ? $this->schedules->where('is_active', true)->map(function ($s) {
                    return [
                        'start_time' => substr($s->start_time, 0, 5),
                        'end_time'   => substr($s->end_time, 0, 5),
                    ];
                })->unique(function ($item) {
                    return $item['start_time'] . '-' . $item['end_time'];
                })->values()->toArray()
                : [],
        ];
    }

    private function getAvailableDates(): array
    {
        $dates = [];
        $rules = $this->rules;
        $maxDays = ($rules && $rules->max_advance_booking_days > 0) ? (int) $rules->max_advance_booking_days : 30;
        $allowSameDay = $rules ? (bool) $rules->allow_same_day : false;

        $startDate = $allowSameDay ? \Carbon\Carbon::today() : \Carbon\Carbon::tomorrow();
        $endDate = \Carbon\Carbon::today()->addDays($maxDays);

        $availableDays = $this->relationLoaded('schedules') && $this->schedules 
            ? $this->schedules->where('is_active', true)->pluck('day')->unique()->map(fn($day) => strtolower($day))->toArray()
            : [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dayName = strtolower($date->format('l'));
            if (in_array($dayName, $availableDays)) {
                $dates[] = $date->toDateString() . ' 00:00:00';
            }
        }

        return $dates;
    }

    private function getPreferredWorkers()
    {
        $userId = auth('sanctum')->id();
        if (!$userId) {
            return collect();
        }

        $preferredIds = \App\Models\BookingAssignment::whereHas('booking', function ($q) use ($userId) {
                $q->where('customer_id', $userId)
                  ->where('service_id', $this->id)
                  ->where('status', 'completed');
            })
            ->pluck('worker_id')
            ->unique()
            ->toArray();

        return $this->workers()
            ->where('status', 'active')
            ->whereIn('workers.id', $preferredIds)
            ->get();
    }
}
