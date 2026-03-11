<?php

namespace App\Http\Resources\Api\V1\Category;

use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResourse extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */ 
    public function toArray(Request $request): array
    { 
        $media = $this->media()->where('collection', 'category/icon')->first();
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'image' => $media ? ( new MediaResource($media))->getFileUrl() : null,
            'status' => $this->is_active ? 'active' : 'inactive',
            'sort_order' => $this->sort_order,
        ];
    }
}
