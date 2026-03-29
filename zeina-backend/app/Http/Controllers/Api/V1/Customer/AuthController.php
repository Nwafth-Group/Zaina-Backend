<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CustomerLoginRequest;
use App\Http\Requests\Api\V1\Customer\CustomerRegisterRequest;
use App\Http\Requests\Api\V1\Customer\CustomerVerifyLoginRequest;
use App\Http\Requests\Api\V1\Customer\CustomerVerifyRegisterRequest;
use App\Http\Requests\Api\V1\Customer\SendOtpRequest;
use App\Http\Requests\Api\V1\Customer\VerifyOtpRequest;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    use ApiResponseTrait;
//    public function register(CustomerRegisterRequest $request){
     
//        $data = $request->validated();
//        $mobile = $data['mobile_number'];
   
//         if(RateLimiter::tooManyAttempts('send-otp'.$mobile,1)){
//             return $this->errorResponse(__('messages.too_many_attempts'));
//         }
//         $otp = app()->environment('local') ? '123456' : rand(100000, 999999);
//         Cache::put('register_data_'.$mobile,[
//             'user_data'=>$data,
//             'otp'=>$otp
//         ]);
//         RateLimiter::hit('send-otp'.$mobile, 60);

//         return $this->successResponse(['otp'=>$otp],__('messages.otp_sent'));//locale now return otp in response 
//    }

   public function sendOtp(SendOtpRequest $request)
    {
        $mobile = $request->validated()['mobile_number'];

        // حماية من إرسال رسائل متكررة (Spam)
        if (RateLimiter::tooManyAttempts('send_otp_'.$mobile, 1)) {
            return $this->errorResponse(__('messages.too_many_attempts'));
        }
        // توليد الكود: 123456 في بيئة التطوير، ورقم عشوائي في السيرفر الحقيقي
        $otp = app()->environment('local') ? '123456' : rand(100000, 999999);
        
        // حفظ الكود في الكاش لمدة 10 دقائق
        Cache::put('auth_otp_'.$mobile, $otp, now()->addMinutes(10));
        RateLimiter::hit('send_otp_'.$mobile, 60);
        // هنا مستقبلاً بتستدعي خدمة الـ SMS (مثل Twilio أو Unifonic) لإرسال الـ $otp
        return $this->successResponse(['otp' => $otp], __('messages.otp_sent'));
    }

//    public function verifyRegister(CustomerVerifyRegisterRequest $request)
//     {
//        $data = $request->validated();
      
//         $mobile = $request->mobile_number;
//         $cachedData = Cache::get('register_data_'.$mobile);

//         if (!$cachedData) {
//             return $this->errorResponse(__('messages.otp_expired'));
//         }

//         if ($cachedData['otp'] != $request->otp) {
//             return $this->errorResponse(__('messages.invalid_otp'));
//         }

//         // نجاح: إنشاء المستخدم في قاعدة البيانات
//         $user = User::create([
//             'first_name' => $cachedData['user_data']['first_name'],
//             'last_name' => $cachedData['user_data']['last_name'],
//             'phone_number' => $mobile,
//             'preferred_language' => $cachedData['user_data']['preferred_language'],
//             'type' => 'customer',
//             'status' => 'active',
//         ]);

//         Cache::forget('register_data_'.$mobile);
//         $token = $user->createToken('customer_token')->plainTextToken;

//         return $this ->successResponse(['token' => $token, 'user' =>new UserResource($user)],
//          __('messages.register_success'));
//     } 
    // public function login(CustomerLoginRequest $request)
    // {
    //     $data = $request->validated();
    //      $auth = User::query()->where('phone_number', $data['mobile_number'])->first();
    //      if(!$auth){
    //         return $this->errorResponse(__('messages.mobile_number_notexsits'));
    //      }
    //     $mobile = $data['mobile_number'];
    //     if (RateLimiter::tooManyAttempts('send_otp_'.$mobile, 1)) {
    //         return $this->errorResponse(__('messages.too_many_attempts'));
    //     }
    //     $otp = app()->environment('local') ? '123456' : rand(100000, 999999);

    //     Cache::put('login_otp_'.$mobile, $otp, now()->addMinutes(10));
    //     RateLimiter::hit('send_otp_'.$mobile, 60);

    //    return $this->successResponse(['otp'=>$otp],__('messages.otp_sent'));
    // }

    // public function verifyLogin(CustomerVerifyLoginRequest $request)
    // {   
    //     $data = $request->validated();
    //     $mobile = $data['mobile_number'];
    //     $attemptsKey = 'login_attempts_'.$mobile;

    //     if (RateLimiter::tooManyAttempts($attemptsKey, 3)) {
    //         return $this->errorResponse(__('messages.too_many_attempts'));
    //     }

    //     $cachedOtp = Cache::get('login_otp_'.$mobile);

    //     if (!$cachedOtp) {
    //         return $this->errorResponse(__('messages.otp_expired'));
    //     }

    //     if ($cachedOtp != $data['otp']) {
    //         RateLimiter::hit($attemptsKey, 600); // حظر 10 دقائق بعد 3 محاولات
    //         return $this->errorResponse(__('messages.invalid_otp'));
    //     }

    //     Cache::forget('login_otp_'.$mobile);
    //     RateLimiter::clear($attemptsKey);

    //     $user = User::where('phone_number', $mobile)->first();

    //     // BR-03: منع الدخول إذا كان الحساب موقوف
    //     if ($user->status !== 'active') {
    //         return $this->errorResponse(__('messages.account_inactive'), 403);
    //         // return response()->json(['message' => 'عفواً، حسابك غير فعال.'], 403);
    //     }

    //     $token = $user->createToken('customer_token')->plainTextToken;

    //     return $this->successResponse(['token' => $token, 'user' => new UserResource($user)], __('messages.login_success'));
    // }

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
            'user' => new UserResource($user),
            'is_new_user' => $isNewUser // هذا المتغير سيخبر تطبيق الموبايل إذا كان يجب تحويل العميل لشاشة "إكمال البروفايل"
        ], $isNewUser ? __('messages.register_success') : __('messages.login_success'));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse(null, __('messages.logout_success'));
    }


}
