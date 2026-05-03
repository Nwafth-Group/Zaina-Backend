<?php

namespace App\Http\Resources\Api\V1\Address;

use App\Http\Resources\Api\V1\Area\AreaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {  
        $this->load([
            'area.city', // جلب تفاصيل المنطقة والمدينة المرتبطة بها
        ]);
        return [
            'id' => $this->id,
            'area' => new AreaResource($this->area), // استخدام AreaResource لعرض تفاصيل المنطقة والمدينة
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'label' => $this->label,
            'street' => $this->street,
            'building_name' => $this->building_name,
            'building_no' => $this->building_no,
            'floor' => $this->floor,
            'apartment_no' => $this->apartment_no,
            'contact_name' => $this->contact_name,
            'phone_number' => $this->phone_number,
            'secondary_phone' => $this->secondary_phone,
            'notes' => $this->notes,
            'is_default' => $this->is_default,
             'created_at' => $this->created_at->toDateTimeString(),
             'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
