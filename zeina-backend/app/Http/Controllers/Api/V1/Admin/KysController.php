<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\Media;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KysController extends Controller
{
    use ApiResponseTrait;

    /**
     * 1. عرض قائمة العملاء الذين لديهم طلبات KYC قيد المراجعة
     */
    public function index()
    {
        $pendingUsers = User::where('type', 'customer')
            ->where('verification_status', 'pending')
            ->latest()
            ->paginate(15);

        return $this->successResponse(UserResource::collection($pendingUsers), __('messages.kyc_pending_users_fetched_successfully'));
    }

    /**
     * 2. عرض تفاصيل طلب العميل (لإرجاع روابط الصور الآمنة)
     */
    public function show($id)
    {
        $user = User::where('verification_status', 'pending')->findOrFail($id);

        // جلب الصور الخاصة بالهوية
        $idFront = $user->media()->where('collection', 'kyc/id_front')->latest()->first();
        $idBack = $user->media()->where('collection', 'kyc/id_back')->latest()->first();
        $selfie = $user->media()->where('collection', 'kyc/selfie')->latest()->first();

        return $this->successResponse([
            'user_id' => $user->id,
            'full_name' => $user->full_name,
            'phone' => $user->phone_number,
            // نولد رابط آمن للدالة المسؤولة عن عرض الصور الخاصة
            'images' => [
                'id_front' => $idFront ? url("api/v1/admin/kyc/media/{$idFront->id}") : null,
                'id_back' => $idBack ? url("api/v1/admin/kyc/media/{$idBack->id}") : null,
                'selfie' => $selfie ? url("api/v1/admin/kyc/media/{$selfie->id}") : null,
            ]
        ]);

    }

    public function approve($id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'verification_status' => 'approved',
            'rejection_reason' => null // تنظيف أي سبب رفض سابق
        ]);

        // إسناد نقاط الإحالة في حال وجد مفيّد
        \App\Service\LoyaltyPointService::awardPointsForReferralApproval($user);

        // 1. إرسال SMS للعميل
        try {
            $smsMessage = __('sms.kyc_approved_sms', ['name' => $user->first_name]);
            app(\App\Service\SmsService::class)->send($user->phone_number, $smsMessage);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send KYC approval SMS to {$user->id}: " . $e->getMessage());
        }

        // 2. إرسال بريد إلكتروني للعميل
        try {
            if (!empty($user->email)) {
                $companyInfo = \App\Models\CompanyInfo::with('media')->first();
                \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\KycApprovedMail($user, $companyInfo));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send KYC approval email to {$user->id}: " . $e->getMessage());
        }

        // 3. إرسال إشعار لحظي (Push Notification) وإضافته لـ Inbox التنبيهات
        try {
            $user->notify(new \App\Notifications\KycStatusNotification('approved'));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send KYC approval notification to {$user->id}: " . $e->getMessage());
        }

        return $this->successResponse(null, __('messages.kyc_approval_successfully'));
    }

    /**
     * 4. رفض الطلب مع ذكر السبب
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500' // إجبار الأدمن على كتابة السبب
        ], [
            'reason.required' => __('messages.reason_required')
        ]);

        $user = User::findOrFail($id);

        $user->update([
            'verification_status' => 'rejected',
            'rejection_reason' => $request->reason
        ]);

        // 1. إرسال SMS للعميل مبيناً سبب الرفض
        try {
            $smsMessage = __('sms.kyc_rejected_sms', ['name' => $user->first_name, 'reason' => $request->reason]);
            app(\App\Service\SmsService::class)->send($user->phone_number, $smsMessage);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send KYC rejection SMS to {$user->id}: " . $e->getMessage());
        }

        // 2. إرسال بريد إلكتروني للعميل
        try {
            if (!empty($user->email)) {
                $companyInfo = \App\Models\CompanyInfo::with('media')->first();
                \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\KycRejectedMail($user, $request->reason, $companyInfo));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send KYC rejection email to {$user->id}: " . $e->getMessage());
        }

        // 3. إرسال إشعار لحظي (Push Notification) وإضافته لـ Inbox التنبيهات
        try {
            $user->notify(new \App\Notifications\KycStatusNotification('rejected', $request->reason));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send KYC rejection notification to {$user->id}: " . $e->getMessage());
        }

        return $this->successResponse(null, __('messages.kyc_rejection_successfully'));
    }

    /**
     * 5. الحارس الأمني: دالة لضخ الصور الخاصة من الديسك (Private)
     */
    public function viewPrivateMedia($mediaId)
    {
        $media = Media::findOrFail($mediaId);

        // تأكيد إضافي: نمنع الوصول لأي ميديا إلا إذا كانت على الديسك الخاص وتخص الـ KYC
        if ($media->disk !== 'private' || !str_contains($media->collection, 'kyc')) {
            return response()->json(['message' => 'غير مصرح بالوصول لهذا الملف.'], 403);
        }

        if (!Storage::disk('private')->exists($media->path)) {
            return response()->json(['message' => 'الملف غير موجود.'], 404);
        }

        // سحر لارافل: قراءة الملف من السرداب وعرضه في المتصفح وكأنه صورة عادية
        return Storage::disk('private')->response($media->path);
    }
}
