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

        // When listed via the recipient relationship the pivot carries read state;
        // fall back to a lookup if this resource is built outside that context.
        $isRead = $this->pivot
            ? ! is_null($this->pivot->read_at)
            : auth('sanctum')->user()?->readAnnouncements()
                ->wherePivotNotNull('read_at')
                ->where('announcements.id', $this->id)
                ->exists();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'title_ar' => $this->attributes['title_ar'] ?? $this->attributes['title'] ?? '',
            'title_en' => $this->attributes['title_en'] ?? $this->attributes['title'] ?? '',
            'body_ar' => $this->attributes['body_ar'] ?? $this->attributes['body'] ?? '',
            'body_en' => $this->attributes['body_en'] ?? $this->attributes['body'] ?? '',
            'image' => $media ? (new MediaResource($media))->getFileUrl() : null,
            'is_read' => (bool) $isRead,
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}
