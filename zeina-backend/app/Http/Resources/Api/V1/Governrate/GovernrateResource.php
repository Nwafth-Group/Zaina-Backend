<?php

namespace App\Http\Resources\Api\V1\Governrate;

use App\Http\Resources\Api\V1\Contry\CountryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GovernrateResource extends JsonResource
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
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'status' => $this->status,
            'country' => new CountryResource($this->whenLoaded('country')),
        ];
    }
}
