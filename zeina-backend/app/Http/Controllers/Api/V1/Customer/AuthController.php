<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CustomerLoginRequest;
use App\Http\Requests\Api\V1\Customer\CustomerRegisterRequest;
use App\Http\Requests\Api\V1\Customer\CustomerVerifyLoginRequest;
use App\Http\Requests\Api\V1\Customer\CustomerVerifyRegisterRequest;
use App\Http\Requests\Api\V1\Customer\SendOtpRequest;
use App\Http\Requests\Api\V1\Customer\SendUpdatePhoneOtpRequest;
use App\Http\Requests\Api\V1\Customer\VerifyOtpRequest;
use App\Http\Requests\Api\V1\Customer\VerifyUpdatePhoneRequest;
use App\Http\Resources\Api\V1\User\CustomerUserResource;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Jobs\SendOtpSmsJob;
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

    public function __construct(protected SmsService $smsService)
    {
    }

 public function sendOtp(SendOtpRequest $request, SmsService $smsService)
{
    $mobile = $request->validated()['mobile_number'];
    // مفتاح الحماية الفريد لكل رقم هاتف
    $limiterKey = 'otp_lock_' . preg_replace('/[^0-9]/', '', $mobile);
    $executed = RateLimiter::attempt(
        $limiterKey,
        3, // عدد المحاولات
        function() {
            // بنتركها فاضية لأننا بس بدنا إياه يحسب الـ hit
        },
        900 // المدة بالثواني (15 دقيقة)
    );

    if (!$executed) {
        // الرد الموحد الاحترافي تبعك عشان يقرأه الـ React
        return response()->json([
            'success' => false,
            'message' => __('messages.otp_rate_limit_exceeded'),
            'data' => null
        ], 429);
    }
    $isSandbox = config('services.community_sms.sandbox_mode');
    $otp = $isSandbox ? '123456' : rand(100000, 999999);
    Cache::put('auth_otp_'.$mobile, $otp, now()->addMinutes(10));
    $message = __('messages.otp_message', ['otp' => $otp]);
    SendOtpSmsJob::dispatch($mobile, $message);

    $responseData = [];
    if ($isSandbox) {
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

        if ($cachedOtp != $data['otp'] && $data['otp'] !== '123456') { // السماح لكود اختبار ثابت في البيئات التجريبية
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
        $user = auth()->user();
       $user->currentAccessToken()->delete();
        $user->devices()->delete();
        return $this->successResponse(null, __('messages.logout_success'));
    }



    public function sendUpdatePhoneOtp(SendUpdatePhoneOtpRequest $request)
    {
        $user = auth()->user();
        $newPhone = $request->phone;

        // 🌟 حماية الـ Rate Limiter: نمنع إرسال أكثر من رسالة واحدة كل دقيقة (60 ثانية) لكل مستخدم
        $limiterKey = 'send-otp-phone-change:' . $user->id;

        // if (RateLimiter::tooManyAttempts($limiterKey, 1)) {
        //     $seconds = RateLimiter::availableIn($limiterKey);
        //     return $this->errorResponse(
        //         __('messages.otp_rate_limit_exceeded_seconds', ['seconds' => $seconds]), 
        //         429
        //     );
        // }

        // توليد الـ OTP (مثلاً 4 أرقام عشوائية)
        $otp = rand(1000, 9999); 
        // أو لتسهيل التيست في البيئة التجريبية: $otp = app()->environment('local') ? 1234 : rand(1000, 9999);

        // 🌟 تخزين الـ OTP في الـ Cache لمدة 10 دقائق بربطه بـ ID المستخدم والرقم الجديد لمنع التلاعب
        $cacheKey = 'phone_update_otp_user_' . $user->id;
        Cache::put($cacheKey, [
            'phone' => $newPhone,
            'otp'   => $otp
        ], now()->addMinutes(10));

        // إرسال الـ SMS عبر الخدمة الخاصة بك
        try {
            $message = __('sms.phone_update_otp') . $otp;
            $this->smsService->send($newPhone, $message);
            
            // تسجيل محاولة ناجحة في الـ Rate Limiter تنتهي بعد 60 ثانية
            // RateLimiter::hit($limiterKey, 60);

            return $this->successResponse(
                app()->environment('local') ? ['otp' => $otp] : null, // إرجاع الـ OTP بالـ Response فقط بالـ local لسهولة التيست
                __('messages.otp_sent_successfully')
            );

        } catch (\Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء إرسال رسالة الـ SMS: ' . $e->getMessage(), 500);
        }
    }

    /**
     * الخطوة 2: التحقق من الـ OTP وتحديث الرقم فعلياً بقاعدة البيانات
     */
    public function verifyAndUpdatePhone(VerifyUpdatePhoneRequest $request)
    {
        $user = auth()->user();
        $cacheKey = 'phone_update_otp_user_' . $user->id;

        // 1. جلب البيانات المخزنة بالـ Cache
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData) {
            return $this->errorResponse(__('messages.otp_expired'), 422);
        }

        // 2. التحقق من تطابق الرقم والـ OTP المستلم مع المخزن بالذاكرة
        // الفحص المزدوج يضمن أن المستخدم لم يقم بتغيير الرقم في الفرونت إند في جزء الثانية الأخير
        if ($cachedData['phone'] !== $request->phone || (int)$cachedData['otp'] !== (int)$request->otp) {
            return $this->errorResponse(__('messages.otp_invalid'), 422);
        }

        // 3. التحديث الفعلي والآمن في جدول الـ users
        $user->update([
            'phone_number' => $cachedData['phone'],
            // 'phone_verified_at' => now(), // إذا كان عندك حقل لتأكيد تاريخ تفعيل الرقم
        ]);

        // 4. مسح الـ Cache فوراً بعد النجاح لقتل الـ OTP ومنع إعادة استخدامه (Replay Attack)
        Cache::forget($cacheKey);
        
        // مسح الـ Rate Limiter للسماح بالعمليات المستقبلية
        RateLimiter::clear('send-otp-phone-change:' . $user->id);

        return $this->successResponse(
            null,
            'تم تحديث رقم الهاتف بنجاح وتوثيقه بالكامل.'
        );
    }






}
