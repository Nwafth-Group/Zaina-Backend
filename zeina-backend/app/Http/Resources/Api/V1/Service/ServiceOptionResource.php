<?php

namespace App\Http\Resources\Api\V1\Service;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
        ];

        if (!empty($this->description_ar)) {
            $data['description_ar'] = $this->description_ar;
        }

        if (!empty($this->description_en)) {
            $data['description_en'] = $this->description_en;
        }

        $data['unit_price'] = (float) $this->unit_price;
        $data['min_qty'] = $this->min_qty !== null ? (int) $this->min_qty : null;
        $data['max_qty'] = $this->max_qty !== null ? (int) $this->max_qty : null;
        $data['status'] = $this->status;
        $data['sort_order'] = (int) $this->sort_order;

        return $data;
    }
}
