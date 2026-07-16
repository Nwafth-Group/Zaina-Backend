<?php

namespace App\Http\Resources\Api\V1\User;

use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class UserResource extends JsonResource
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

        // 🌟 استخراج مستندات التوثيق وصورة البروفايل من علاقة الميديا المحملة مسبقاً (لحماية الأداء)
        // استخدمنا filter و first بدلاً من ضرب قاعدة البيانات بـ Query جديدة
        $profileImage = $this->media->where('collection', 'user/profilePhoto')->first();
        $idFront      = $this->media->where('collection', 'kyc/id_front')->first();
        $idBack       = $this->media->where('collection', 'kyc/id_back')->first();
        $selfie       = $this->media->where('collection', 'kyc/selfie')->first();


            $getSecureUrl = function($mediaItem) {
            if (!$mediaItem) return null;
            
            // إذا كان الملف مرفوعاً على الديسك الخاص (المحمي)
            if ($mediaItem->disk === 'private') {
                // نولد رابطاً مؤقتاً ومحمياً يمر عبر Controller خاص بالتحقق بصلاحية تنتهي بعد 30 دقيقة
                return URL::temporarySignedRoute(
                    'admin.kyc.download', // اسم الراوت المسؤول عن جلب الصورة
                    now()->addMinutes(30), 
                    ['media' => $mediaItem->id]
                );
            }
            // إذا كان ديسك عادي (مثل صورة البروفايل العامة)
            return (new MediaResource($mediaItem))->getFileUrl();
        };

        return [
            'id'                     => $this->id,
            'id_number'              => $this->id_number,
            'first_name'             => $this->first_name,
            'last_name'              => $this->last_name,
            'full_name'              => $this->full_name,
            'verification_status'    => $this->verification_status, // unverified, pending, approved, rejected
            'rejection_reason'       => $this->rejection_reason,
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
            'image'                  => $profileImage ? (new MediaResource($profileImage))->getFileUrl() : null,
            
            // 🌟 مصفوفة مستندات التحقق الخاصة بالأدمن (تظهر فقط إذا كانت الصور مرفوعة فعلياً)
            'verification_documents' => [
                    'id_front' => $getSecureUrl($idFront),
                    'id_back'  => $getSecureUrl($idBack),
                    'selfie'   => $getSecureUrl($selfie),
            ],

            'invited_by'             => $this->referredBy ? [
                'id'            => $this->referredBy->id,
                'full_name'     => $this->referredBy->full_name,
                'email'         => $this->referredBy->email,
                'phone_number'  => $this->referredBy->phone_number,
                'referral_code' => $this->referredBy->activeReferralCode(),
            ] : null,

            'created_at'             => $this->created_at?->toDateTimeString(),
            'updated_at'             => $this->updated_at?->toDateTimeString(),
        ];
    }

    
}