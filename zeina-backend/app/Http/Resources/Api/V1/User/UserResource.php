<?php

namespace App\Http\Resources\Api\V1\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'id_number' => $this->id_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' =>$this->full_name,
            'gender' => $this->gender,
            'email' =>$this->email,
            'mobile_number' => $this->phone_number,
            'preferred_language' => $this->preferred_language,
            'type' => $this->type,
            'status' => $this->status,
             'created_at' => $this->created_at->toDateTimeString(),
             'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
