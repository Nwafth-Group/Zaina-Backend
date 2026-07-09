<?php

namespace App\Http\Resources\Api\V1\Worker;

use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $media = $this->media->where('collection', 'worker/image')->first();

        $service = $this->relationLoaded('services') ? $this->services->first() : $this->services()->first();
        $assignedService = $service ? [
            'id' => $service->id,
            'name_ar' => $service->name_ar,
            'name_en' => $service->name_en,
        ] : 'Not Assigned';

        return [
            'id' => $this->id,
            'name' => $this->name,
            'nationality' => $this->nationality,
            'rating' => $this->avg_rating,
            'status' => $this->status,
            'assigned_service' => $assignedService,
            'image' => $media ? (new MediaResource($media))->getFileUrl() : null,
        ];
    }
}
