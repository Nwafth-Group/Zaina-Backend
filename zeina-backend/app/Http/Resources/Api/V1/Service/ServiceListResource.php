<?php

namespace App\Http\Resources\Api\V1\Service;

use App\Http\Resources\Api\V1\Category\CategoryResourse;
use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceListResource extends JsonResource
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
            'category' => new CategoryResourse($this->whenLoaded('category')),
            'type' => $this->type,
            'service_code' => $this->service_code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'status' => $this->status,
            'avg_rating' => $this->avg_rating,
            'default_duration' => $this->default_duration,
            'duration_unit' => $this->duration_unit,
            'base_price' => $this->base_price,
            'image' => $media ? (new MediaResource($media))->getFileUrl() : null,
            'price_details' => [
                'price_per_service_period' => $priceObj ? $priceObj->price_per_service_period : 0,
                'currency' => $priceObj ? $priceObj->currency : 'EGP',
                'is_starting_price' => $isStartingPrice,
            ],
        ];
    }
}
