<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CompleteProfileRequest;
use App\Http\Requests\Api\V1\Customer\DeleteAccountRequest;
use App\Http\Resources\Api\V1\User\CustomerUserResource;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Service\MediaService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Events\AuditCustom;

class ProfileController extends Controller
{    
    use ApiResponseTrait;

     public function __construct(protected MediaService $mediaService){

     }
   public function show()
    {   
        $user = auth('sanctum')->user();
       
        return $this->successResponse(
            new CustomerUserResource($user),
            __('messages.profile_fetched_successfully')
        );
    }

    // دالة تحديث بيانات البروفايل
    public function completeProfile(CompleteProfileRequest $request)
    {
        $user = auth()->user();

        $updateData = [
            'first_name'   => $request->first_name,
            'last_name'    => $request->last_name,
            'phone_number' => $request->phone_number,
            'email'        => $request->email,
            'id_number'    => $request->id_number,
            'gender'       => $request->gender, 
        ];

        // 1. تحديث البيانات النصية في الداتابيز
        $user->update($updateData);

        // 2. تحديث صورة الملف الشخصي (Profile Picture)
        if($request->hasFile('image')) {
            if ($user->media()->where('collection', 'user/profilePhoto')->exists()) {
                $oldMedia = $user->media()->where('collection','user/profilePhoto')->first();
                $this->mediaService->delete($oldMedia);
            }
            $media = $this->mediaService->upload($request->file('image'), $user, 'user/profilePhoto', 'public');
        }

        return $this->successResponse(
            new CustomerUserResource($user),
            __('messages.profile_updated_successfully')
        );
    }

    public function sendDeleteOtp(Request $request)
    {
        $user = auth()->user();
        $mobile = $user->phone_number;
        $resendKey = 'resend_delete_otp_'.$mobile;

       
        if (RateLimiter::tooManyAttempts($resendKey, 1)) {
            return $this->errorResponse(__('messages.otp_resend_limit_exceeded'));
        }

        $otp = app()->environment('local') ? '123456' : rand(100000, 999999);

        // حفظ الكود في الكاش لمدة 5 دقائق
        Cache::put('delete_otp_' . $mobile, $otp, now()->addMinutes(5));
        RateLimiter::hit($resendKey, 60);

        return $this->successResponse(null,__('messages.otp_sent'));
    }

    public function deleteAccount(DeleteAccountRequest $request)
    {
        $user = auth()->user();
        $mobile = $user->phone_number;

        // --- 1. التحقق من الـ OTP ---
        $cachedOtp = Cache::get('delete_otp_'.$mobile);
        
        if (!$cachedOtp || $cachedOtp != $request->otp) {

        return $this->errorResponse(__('messages.invalid_otp'),400);

        }

        // --- 2. فحص القيود (Eligibility Checks) ---
        // فحص هل يوجد حجوزات نشطة للعميل (يمنع الحذف لو موجودة)
        // افترض أننا نملك علاقة bookings في الموديل
        /* $hasActiveBookings = $user->bookings()->whereIn('status', ['new', 'confirmed', 'on_way', 'in_progress'])->exists();
        if ($hasActiveBookings) {
            return response()->json(['message' => __('messages.cannot_delete_active_booking')], 403);
        }
        */

        // --- 3. توثيق سبب الحذف عبر مكتبة owen-it/laravel-auditing ---
        // ننشئ حدث مخصص (Custom Event) لنرمي فيه سبب الحذف جوا جدول الـ audits
        $user->auditEvent = 'account_deletion_requested';
        $user->isCustomEvent = true;
        $user->auditCustomOld = [];
        $user->auditCustomNew = [
            'reason'       => $request->reason,
            'other_reason' => $request->other_reason,
            'action_note'  => 'User requested account deletion via App'
        ];
        // إطلاق الحدث ليتم الحفظ في الداتا بيس
        Event::dispatch(new AuditCustom($user));


        // --- 4. إخفاء البيانات (Anonymization) والحذف الوهمي ---
        Cache::forget('delete_otp_' . $mobile);
        
        // طمس البيانات الشخصية (حسب قوانين حماية البيانات والـ BRD)
        // $user->update([
        //     'first_name' => 'Deleted',
        //     'last_name'  => 'Customer',
        //     'email'      => null,
        //     'status'     => 'inactive', 
        // ]);

        // الحذف الوهمي (Soft Delete)
        $user->delete(); 

        // --- 5. تسجيل الخروج وإلغاء التوكن ---
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, __('messages.account_deleted'));
    }
}
