<?php

namespace App\Http\Resources\Api\V1\Service;

use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Http\Resources\Api\V1\Worker\WorkerResource;
use App\Models\BookingAssignment;
use Carbon\Carbon;
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

        $defaultAddressId = null;
        if (auth('sanctum')->check()) {
            $user = auth('sanctum')->user();
            $defaultAddress = $user->addresses()->where('is_default', true)->first()
                ?? $user->addresses()->first();
            if ($defaultAddress) {
                $defaultAddressId = $defaultAddress->id;
            }
        }

        return [
            'id' => $this->id,
            'address_id' => $defaultAddressId,
            'type' => $this->type,
            'worker_gender_options' => $this->worker_gender_options ?? [],
            'pricing_type' => $this->pricing_type ?? 'per_service',
            'service_code' => $this->service_code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'features' => $this->features ?? [],
            'avg_rating' => $this->avg_rating,
            'status' => $this->status,

            // New Configuration Parameters
            'available_visit_types' => collect($this->available_visit_types ?? [])->map(function ($item) {
                return [
                    'type' => $item['type'] ?? '',
                    'discount' => isset($item['discount']) ? (float) $item['discount'] : 0.0,
                ];
            })->toArray(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'default_duration' => $this->default_duration,
            'has_duration_range' => (bool) $this->has_duration_range,
            'min_duration' => $this->min_duration,
            'max_duration' => $this->max_duration,
            'min_workers' => $this->min_workers,
            'max_workers' => $this->max_workers,
            'min_children' => $this->min_children,
            'max_children' => $this->max_children,
            'covered_persons_per_worker' => $this->covered_persons_per_worker ? (int) $this->covered_persons_per_worker : 1,
            'base_price' => $this->base_price,
            'terms_ar' => $this->terms_ar,
            'terms_en' => $this->terms_en,

            'duration' => ServiceDurationResource::make($this->whenLoaded('duration')),
            'rules' => ServiceRuleResource::make($this->whenLoaded('rules')),
            'reward_rule' => ($serviceRewardRule = \App\Models\RewardTasks::where('status', true)->where('rule_category', 'service')->where('service_id', $this->id)->first()) ? [
                'id' => $serviceRewardRule->id,
                'title_ar' => $serviceRewardRule->title_ar,
                'title_en' => $serviceRewardRule->title_en,
                'subtitle_ar' => $serviceRewardRule->subtitle_ar,
                'subtitle_en' => $serviceRewardRule->subtitle_en,
                'points' => (int) $serviceRewardRule->points,
                'required_booking_count' => (int) ($serviceRewardRule->required_booking_count ?? 1),
                'frequency_type' => $serviceRewardRule->frequency_type ?? 'repeatable',
            ] : null,
            'child_age_groups' => ServiceChildAgeGroupResource::collection($this->childAgeGroups),
            'coverages' => ServiceCoverageResource::collection($this->whenLoaded('coverages')),
            'prices' => ServicePriceResource::collection($this->whenLoaded('priceRules')),
            'image' => $media ? MediaResource::make($media)->getFileUrl() : null,

            'preferred_workers' => $this->relationLoaded('preferred_workers')
                ? WorkerResource::collection($this->preferred_workers)
                : WorkerResource::collection($this->getPreferredWorkers()),
            'workers' => $this->when(str_contains($request->path(), 'admin'), WorkerResource::collection($this->whenLoaded('workers'))),
            'schedules' => ServiceScheduleResource::collection($this->whenLoaded('schedules')),
            'available_dates' => $this->getAvailableDates(),
            'available_days' => $this->relationLoaded('schedules') && $this->schedules
                ? $this->schedules->where('is_active', true)->pluck('day')->unique()->values()->toArray()
                : [],
            'available_shifts' => $this->relationLoaded('schedules') && $this->schedules
                ? $this->schedules->where('is_active', true)->map(function ($s) {
                    return [
                        'start_time' => substr($s->start_time, 0, 5),
                        'end_time' => substr($s->end_time, 0, 5),
                    ];
                })->unique(function ($item) {
                    return $item['start_time'].'-'.$item['end_time'];
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

        $startDate = $allowSameDay ? Carbon::today() : Carbon::tomorrow();
        $endDate = Carbon::today()->addDays($maxDays);

        $availableDays = $this->relationLoaded('schedules') && $this->schedules
            ? $this->schedules->where('is_active', true)->pluck('day')->unique()->map(fn ($day) => strtolower($day))->toArray()
            : [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dayName = strtolower($date->format('l'));
            if (in_array($dayName, $availableDays)) {
                $dates[] = $date->toDateString().' 00:00:00';
            }
        }

        return $dates;
    }

    private function getPreferredWorkers()
    {
        $userId = auth('sanctum')->id();
        if (! $userId) {
            return collect();
        }

        $preferredIds = BookingAssignment::whereHas('booking', function ($q) use ($userId) {
            $q->where('customer_id', $userId)
                ->where('service_id', $this->id)
                ->where('status', 'completed');
        })
            ->pluck('worker_id')
            ->unique()
            ->toArray();

        return $this->workers()
            ->where('status', 'active')
            ->whereBetween('avg_rating', [4.0, 5.0])
            ->whereIn('workers.id', $preferredIds)
            ->get();
    }
}
