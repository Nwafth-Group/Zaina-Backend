<?php

namespace App\Http\Resources\Api\V1\Ad;

use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Banner (ad) shape for the admin management area — includes the status so the
 * admin can see and toggle active/inactive banners. Expects `media` eager-loaded.
 */
class AdminAdResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $image = $this->media->where('collection', 'ads/image')->first();

        return [
            'id' => $this->id,
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,
            'type' => $this->type,
            'text_en' => $this->text_en,
            'text_ar' => $this->text_ar,
            'link_type' => $this->link_type,
            'link' => $this->link,
            'image' => $image ? (new MediaResource($image))->getFileUrl() : null,
            'status' => $this->status,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
