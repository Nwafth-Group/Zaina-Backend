<?php

namespace App\Http\Resources\Api\V1\LegalDocument;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminLegalDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'title_ar'   => $this->title_ar,
            'title_en'   => $this->title_en,
            'content_ar' => $this->content_ar,
            'content_en' => $this->content_en,
            'sort_order' => (int) $this->sort_order,
            'is_active'  => (bool) $this->is_active,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
