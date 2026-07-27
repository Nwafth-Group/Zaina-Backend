<?php

namespace App\Http\Resources\Api\V1\Worker;

use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Http\Resources\Api\V1\Service\ServiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $media = $this->media ? ($this->media->where('collection', 'worker/image')->first() ?? $this->media->first()) : null;

        $service = $this->relationLoaded('services') ? $this->services->first() : $this->services()->first();
        $assignedService = $service ? [
            'id' => $service->id,
            'name_ar' => $service->name_ar,
            'name_en' => $service->name_en,
        ] : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone_number' => $this->phone_number,
            'nationality' => $this->nationality,
            'id_number' => $this->id_number,
            'address' => $this->address,
            'gender' => $this->gender,
            'rating' => $this->avg_rating,
            'status' => $this->status,
            'assigned_service' => $assignedService,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'image' => $media ? (new MediaResource($media))->getFileUrl() : null,
        ];
    }
}
