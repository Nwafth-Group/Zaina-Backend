<?php

namespace App\Http\Resources\Api\V1\City;

use App\Http\Resources\Api\V1\Contry\CountryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
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
            'country' => new CountryResource($this->country),
            'govrenrate_id' => $this->govrenrate_id,
            'govrenrate' => $this->whenLoaded('govrenrate', fn () => [
                'id' => $this->govrenrate->id,
                'name_ar' => $this->govrenrate->name_ar,
                'name_en' => $this->govrenrate->name_en,
            ]),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'code' => $this->code,
            'status' => $this->status,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
        ];
    }
}
