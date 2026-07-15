<?php

namespace App\Http\Resources\Api\V1\Ad;

use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $image = $this->media()->where('collection', 'ads/image')->first();

        return [
            'id' => $this->id,
            'link' => null,
            'image' => $image ? (new MediaResource($image))->getFileUrl() : null,
        ];
    }
}
