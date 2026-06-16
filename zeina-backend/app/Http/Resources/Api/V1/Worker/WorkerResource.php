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
        $media = $this->media->where('collection', 'worker/image')->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'nationality' => $this->nationality,
            'rating' => $this->avg_rating,
            'status' => $this->status,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'image' => $media ? (new MediaResource($media))->getFileUrl() : null,
        ];
    }
}
