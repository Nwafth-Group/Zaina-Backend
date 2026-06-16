<?php

namespace App\Http\Resources\Api\V1\Announcement;

use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    { 
        $media = $this->media()->where('collection','announcement/image')->first();
        return [
            'id'=>$this->id,
            'title' => $this->title,
            'body' => $this->body,
            'image' => $media?  (new MediaResource($media))->getFileUrl() : null
        ];
    }
}
