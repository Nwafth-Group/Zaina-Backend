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

            'duration' => new ServiceDurationResource($this->whenLoaded('duration')),
            'rules' => new ServiceRuleResource($this->whenLoaded('rules')),
            'coverages' => ServiceCoverageResource::collection($this->whenLoaded('coverages')),
            'prices' => ServicePriceResource::collection($this->whenLoaded('priceRules')),
            'image' => $media ? (new MediaResource($media))->getFileUrl() : null,
 
            'workers' => WorkerResource::collection($this->whenLoaded('workers')),
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
}
