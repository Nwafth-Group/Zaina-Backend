<?php

namespace App\Http\Resources\Api\V1\User;

use App\Http\Resources\Api\V1\Media\MediaResource;
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
       $roles = $this->roles->pluck('name');
       $primaryRole = $roles->first();
       $otherRolesCount = $roles->count() > 1 ? $roles->count() - 1 : 0;
       $image  = $this->media()->where('collection', 'user/profilePhoto')->first();
        return [
            'id' => $this->id,
            'id_number' => $this->id_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' =>$this->full_name,
            'wallet_balance' => $this->wallet_balance,
            'gender' => $this->gender,
            'email' =>$this->email,
            'mobile_number' => $this->phone_number,
            'preferred_language' => $this->preferred_language,
            'primary_role' => $primaryRole,
            'additional_roles_count' => $otherRolesCount,
            'all_roles'    => $roles,
            'type' => $this->type,
            'status' => $this->status,
            'image'=> $image? (new MediaResource($image))->getFileUrl() : null,
             'created_at' => $this->created_at->toDateTimeString(),
             'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
