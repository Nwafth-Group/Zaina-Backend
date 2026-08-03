<?php

namespace App\Http\Resources\Api\V1\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'title_en'    => $this->title_en,
            'title_ar'    => $this->title_ar,
            'content_en'  => $this->content_en,
            'content_ar'  => $this->content_ar,
            'audience'    => $this->audience,
            'send_date'   => $this->send_date,
            'send_time'   => $this->send_time,
            'platforms'   => $this->platforms,
            'status'      => $this->status,
            'created_at'  => $this->created_at?->toDateTimeString(),
            'updated_at'  => $this->updated_at?->toDateTimeString(),
            'creator_name'=> $this->creator?->full_name ?: ($this->creator?->first_name ? trim($this->creator->first_name . ' ' . $this->creator->last_name) : ($this->creator?->email ?? ($this->created_by ? 'Admin #' . $this->created_by : null))),
            'updater_name'=> $this->updater?->full_name ?: ($this->updater?->first_name ? trim($this->updater->first_name . ' ' . $this->updater->last_name) : ($this->updater?->email ?? ($this->updated_by ? 'Admin #' . $this->updated_by : null))),
        ];
    }
}
