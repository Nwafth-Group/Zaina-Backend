<?php

namespace App\Http\Resources\Api\V1\Service;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceChildAgeGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'age_from' => (int) $this->age_from,
            'age_to' => (int) $this->age_to,
            'calculation_type' => $this->calculation_type,
            'children_count' => (int) $this->children_count,
            'required_worker_count' => (int) $this->required_worker_count,
            'status' => $this->status,
            'note' => $this->note,
        ];
    }
}
