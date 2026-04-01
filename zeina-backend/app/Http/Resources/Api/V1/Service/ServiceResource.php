<?php

namespace App\Http\Resources\Api\V1\Service;

use App\Http\Resources\Api\V1\Category\CategoryResourse;
use App\Http\Resources\Api\V1\Service\ServiceCoverageResource;
use App\Http\Resources\Api\V1\Service\ServiceDurationResource;
use App\Http\Resources\Api\V1\Service\ServiceRuleResource;
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
        return [
                'id' => $this->id,
                'category' => new CategoryResourse($this->whenLoaded('category')),
                'type' => $this->type,
                'name_ar' => $this->name_ar,
                'name_en' => $this->name_en,
                'description_ar' => $this->description_ar,
                'description_en' => $this->description_en,
                'status' => $this->status,
                'avg_rating' => $this->avg_rating,
                'duration' => new ServiceDurationResource($this->whenLoaded('duration')),
                'rules' => new ServiceRuleResource($this->whenLoaded('rules')),
                'coverages' => ServiceCoverageResource::collection($this->whenLoaded('coverages')),
        ];
    }
}
