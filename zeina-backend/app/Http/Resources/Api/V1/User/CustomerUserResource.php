<?php

namespace App\Http\Resources\Api\V1\User;

use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // جلب الأدوار بأمان إذا كانت محملة
        $roles = $this->relationLoaded('roles') ? $this->roles->pluck('name') : collect();
        $primaryRole = $roles->first();
        $otherRolesCount = $roles->count() > 1 ? $roles->count() - 1 : 0;
        
        // جلب صورة الملف الشخصي العامة فقط (مخزنة بالنطاق العام Public)
        $profileImage = $this->media->where('collection', 'user/profilePhoto')->first();

        return [
            'id'                     => $this->id,
            'devices'                => UserDeviceResource::collection($this->devices),
            'id_number'              => $this->id_number,
            'first_name'             => $this->first_name,
            'last_name'              => $this->last_name,
            'full_name'              => $this->full_name,
            'verification_status'    => $this->verification_status, // unverified, pending, approved, rejected
            'rejection_reason'       => $this->rejection_reason,    // يظهر للكستمر فقط لو تم رفضه ليعرف السبب
            'wallet_balance'         => (float) $this->wallet_balance,
            'points'                 => (int) $this->getTotalPointsAttribute(),
            'gender'                 => $this->gender,
            'email'                  => $this->email,
            'mobile_number'          => $this->phone_number,
            'preferred_language'     => $this->preferred_language,
            'primary_role'           => $primaryRole,
            'additional_roles_count' => $otherRolesCount,
            'all_roles'              => $roles,
            'type'                   => $this->type,
            'status'                 => $this->status, // active, inactive
            'referral_code'          => $this->activeReferralCode(),
            'referred_by_id'         => $this->referred_by_id,
            'image'                  => $profileImage ? (new MediaResource($profileImage))->getFileUrl() : null,
            'created_at'             => $this->created_at?->toDateTimeString(),
            'updated_at'             => $this->updated_at?->toDateTimeString(),
        ];
    }
}