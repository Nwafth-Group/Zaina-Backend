<?php

namespace App\Http\Resources\Api\V1\Service;

use App\Http\Resources\Api\V1\Category\CategoryResourse;
use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Http\Resources\Api\V1\Worker\WorkerResource;
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

        // السحر صار بسطر واحد نظيف!
        // إذا كان الأوبجيكت موجود، اقرأ قيمة is_fallback، وإذا مش موجود اعتبره true احتياطياً
        $isStartingPrice = $priceObj ? $priceObj->is_fallback : true;

        return [
            'id' => $this->id,
            'category' => new CategoryResourse($this->whenLoaded('category')),
            'type' => $this->type,
            'service_code' => $this->service_code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'features' => $this->features ?? [],
            'status' => $this->status,
            'avg_rating' => $this->avg_rating,
            
            // New Configuration Parameters
            'available_visit_types' => $this->available_visit_types ?? [],
            'discount_value' => $this->discount_value,
            'terms_ar' => $this->terms_ar,
            'terms_en' => $this->terms_en,
            'acceptance_required' => (bool) $this->acceptance_required,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'default_duration' => $this->default_duration,
            'duration_unit' => $this->duration_unit,
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
 
            'price_details' => [
                'price_per_service_period' => $priceObj ? $priceObj->price_per_service_period : 0,
                'currency' => $priceObj ? $priceObj->currency : 'EGP',
                'is_starting_price' => $isStartingPrice, // true = حط كلمة "يبدأ من"، false = هاد سعره النهائي
            ],
            'workers' => WorkerResource::collection($this->whenLoaded('workers')),
            'schedules' => ServiceScheduleResource::collection($this->whenLoaded('schedules')),
        ];
    }
}
