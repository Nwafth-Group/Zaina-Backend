<?php

namespace App\Http\Resources\Api\V1\Service;

use App\Http\Resources\Api\V1\Category\CategoryResourse;
use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Http\Resources\Api\V1\Service\ServiceCoverageResource;
use App\Http\Resources\Api\V1\Service\ServiceDurationResource;
use App\Http\Resources\Api\V1\Service\ServiceRuleResource;
use App\Http\Resources\Api\V1\Service\ServiceScheduleResource;
use App\Models\Category;
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
                'name_ar' => $this->name_ar,
                'name_en' => $this->name_en,
                'description_ar' => $this->description_ar,
                'description_en' => $this->description_en,
                'features' => $this->features ?? [],
                'status' => $this->status,
                'avg_rating' => $this->avg_rating,
                'duration' => new ServiceDurationResource($this->whenLoaded('duration')),
                'rules' => new ServiceRuleResource($this->whenLoaded('rules')),
                'coverages' => ServiceCoverageResource::collection($this->whenLoaded('coverages')),
                'image' => $media ? (new MediaResource($media))->getFileUrl() : null,
                // 'basePrice' => $this->whenLoaded('startingPrice', function () {
                //     return $this->startingPrice?->base_price;
                // }),
            //     'price' => $this->whenLoaded('startingPrice', function () {
            //     return [
            //         'base_price' => (float) $this->startingPrice?->base_price,
            //         'price_per_hour' => (float) $this->startingPrice?->price_per_hour,
            //         'currency' => $this->startingPrice?->currency,
            //     ];
            //    }),    

            'price_details'  => [
                'base_price'        => $priceObj ? $priceObj->base_price : 0,
                'price_per_hour'    => $priceObj ? $priceObj->price_per_hour : 0,
                'currency'          => $priceObj ? $priceObj->currency : 'EGP',
                'is_starting_price' => $isStartingPrice, // true = حط كلمة "يبدأ من"، false = هاد سعره النهائي
            ],

            'schedules' => ServiceScheduleResource::collection($this->whenLoaded('schedules'))
        ];
    }
}
