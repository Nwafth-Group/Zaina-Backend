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
        // 1. التعامل مع الصورة بأداء عالي (نبحث في الـ Collection المحمّلة مسبقاً بدلاً من ضرب الداتابيز)
        $media = $this->media->where('collection', 'category/icon')->first();

        return [
            'id'         => $this->id,
            'name_ar'    => $this->name_ar,
            'name_en'    => $this->name_en,
            
            // 2. استخدام whenLoaded لمنع مشكلة N+1 (رح يرجع الـ Parent بس إذا طلبناه في الكنترولر مع with)
            'parent'     => $this->whenLoaded('parent', function () {
                return new CategoryResourse($this->parent);
            }),
            
            'image'      => $media ? (new MediaResource($media))->getFileUrl() : null,
            
            // 3. تصحيح حقل الحالة بناءً على المايجريشن
            'status'     => $this->status, 
            
            'sort_order' => $this->sort_order,
        ];
    }
}
