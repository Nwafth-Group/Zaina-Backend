<?php

namespace App\Http\Resources\Api\V1\Announcement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\V1\Media\MediaResource;

class AnnouncementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $media = $this->media()->where('collection', 'announcement/image')->first();

        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'image' => $media ? (new MediaResource($media))->getFileUrl() : null,
            'readers_count' => $this->readers_count
                ?? ($this->relationLoaded('readers') ? $this->readers->count() : 0),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];

        // The edit form needs the exact readers to pre-select them.
        if ($this->relationLoaded('readers')) {
            $data['reader_ids'] = $this->readers->pluck('id');
        }

        return $data;
    }
}
