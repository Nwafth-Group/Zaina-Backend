<?php

namespace App\Http\Resources\Api\V1\LegalDocument;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegalDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {  
        $lang = app()->getLocale();
       return [
            'id'         => $this->id,
            'title'      => $lang === 'ar' ? $this->title_ar : $this->title_en,
            'content'    => $lang === 'ar' ? $this->content_ar : $this->content_en,
            'sort_order' => $this->sort_order,
        ];
    }
}
