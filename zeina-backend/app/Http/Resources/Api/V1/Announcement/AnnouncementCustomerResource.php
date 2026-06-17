<?php

namespace App\Http\Resources\Api\V1\Announcement;

use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementCustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $media = $this->media()->where('collection', 'announcement/image')->first();
        $user = auth('sanctum')->user();
        $readIds = $user->readAnnouncements()->pluck('announcement_id')->toArray();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'image' => $media ? (new MediaResource($media))->getFileUrl() : null,
            'is_read' => in_array($this->id, $readIds),
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}
