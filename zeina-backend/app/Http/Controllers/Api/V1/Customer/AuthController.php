<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CustomerLoginRequest;
use App\Http\Requests\Api\V1\Customer\CustomerRegisterRequest;
use App\Http\Requests\Api\V1\Customer\CustomerVerifyLoginRequest;
use App\Http\Requests\Api\V1\Customer\CustomerVerifyRegisterRequest;
use App\Http\Requests\Api\V1\Customer\SendOtpRequest;
use App\Http\Requests\Api\V1\Customer\VerifyOtpRequest;
use App\Http\Resources\Api\V1\User\CustomerUserResource;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\User;
use App\Service\SmsService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    use ApiResponseTrait;

  public function sendOtp(SendOtpRequest $request, SmsService $smsService)
{
    $mobile = $request->validated()['mobile_number'];

    // حماية من إرسال رسائل متكررة (Spam)
    if (RateLimiter::tooManyAttempts('send_otp_'.$mobile, 1)) {
        return $this->errorResponse(__('messages.too_many_attempts'));
    }
    // توليد الكود
    $otp = app()->environment('local') ? '123456' : rand(100000, 999999);
    // حفظ الكود في الكاش لمدة 10 دقائق
    Cache::put('auth_otp_'.$mobile, $otp, now()->addMinutes(10));
    RateLimiter::hit('send_otp_'.$mobile, 60);
    // صياغة نص الرسالة
    $message = __('messages.otp_message', ['otp' => $otp]);
    // استدعاء الخدمة لإرسال الرسالة الحقيقية بالسيرفر
    $smsSent = $smsService->send($mobile, $message);

    if (!$smsSent && !app()->environment('local')) {
        return $this->errorResponse(__('messages.sms_failed_to_send'), 500);
    }

    // أفضل طريقة للـ Response: نرجع الـ OTP فقط في الـ local عشان الـ testing
    $responseData = [];
    if (app()->environment('local')) {
        $responseData['otp'] = $otp; 
    }

    return $this->successResponse($responseData, __('messages.otp_sent'));
}

        
    public function verifyOtp(VerifyOtpRequest $request)
    {
        $data = $request->validated();
        $mobile = $data['mobile_number'];
        $attemptsKey = 'otp_attempts_'.$mobile;

        // حماية من التخمين العشوائي للكود (Brute-force)
        if (RateLimiter::tooManyAttempts($attemptsKey, 3)) {
            return $this->errorResponse(__('messages.too_many_attempts'));
        }

        $cachedOtp = Cache::get('auth_otp_'.$mobile);

        if (!$cachedOtp) {
            return $this->errorResponse(__('messages.otp_expired'));
        }

        if ($cachedOtp != $data['otp']) {
            RateLimiter::hit($attemptsKey, 600); // حظر 10 دقائق بعد 3 محاولات خاطئة
            return $this->errorResponse(__('messages.invalid_otp'));
        }

        // الكود صحيح ➔ تنظيف الكاش والعداد
        Cache::forget('auth_otp_'.$mobile);
        RateLimiter::clear($attemptsKey);

        $isNewUser = false;
        $user = User::where('phone_number', $mobile)->first();

        // إذا لم يكن المستخدم موجوداً، نقوم بإنشائه (Register)
        if (!$user) {
            $user = User::create([
                'phone_number' => $mobile,
                'type'         => 'customer',
                'status'       => 'active',
                'verification_status' => 'unverified',
                'preferred_language' => 'en',
            ]);
            $isNewUser = true;
        }  

        // BR-03: منع الدخول إذا كان الحساب موقوفاً
        if ($user->status !== 'active') {
            return $this->errorResponse(__('messages.account_inactive'), 403);
        }
        // إنشاء التوكن
        $token = $user->createToken('customer_token')->plainTextToken;
        return $this->successResponse([
            'token' => $token, 
            'user' => new CustomerUserResource($user),
            'is_new_user' => $isNewUser // هذا المتغير سيخبر تطبيق الموبايل إذا كان يجب تحويل العميل لشاشة "إكمال البروفايل"
        ], $isNewUser ? __('messages.register_success') : __('messages.login_success'));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse(null, __('messages.logout_success'));
    }


}
