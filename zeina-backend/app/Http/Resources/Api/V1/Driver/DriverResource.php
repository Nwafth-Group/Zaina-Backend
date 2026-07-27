<?php

namespace App\Http\Resources\Api\V1\Driver;

use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = $this->media ? $this->media->first() : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'gender' => $this->gender,
            'status' => $this->status,
            'image' => $media ? (new MediaResource($media))->getFileUrl() : $this->image_url,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
