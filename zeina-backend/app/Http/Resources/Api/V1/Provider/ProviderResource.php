<?php

namespace App\Http\Resources\Api\V1\Provider;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderResource extends JsonResource
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
            'type' => $this->type,
            'fullname' => $this->fullname,
            'gender'=> $this->gender,
            'phone_number'=> $this->phone_number,
            'phone_code' => $this->phone_code,
            'code'=> $this->code,
            'status'=> $this->status,
            'internal_notes' =>$this->internal_notes,
            'rating_average' => $this->rating_average,
         
        ];
    }
}
