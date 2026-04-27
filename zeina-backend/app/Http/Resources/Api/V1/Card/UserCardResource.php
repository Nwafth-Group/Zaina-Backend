<?php

namespace App\Http\Resources\Api\V1\Card;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserCardResource extends JsonResource
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
        'card_holder_name' => $this->card_holder_name,
        'brand' => strtoupper($this->card_brand),
        'display_number' => '**** **** **** ' . $this->last_four,
        'is_default' => (bool) $this->is_default,
    ];
    }
}
