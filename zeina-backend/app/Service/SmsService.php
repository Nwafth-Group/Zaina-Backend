<?php

namespace App\Service;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsService
{
    protected $username;
    protected $password;
    protected $sender;
    protected $url;

    public function __construct()
    {
        $this->username = config('services.community_sms.username');
        $this->password = config('services.community_sms.password');
        $this->sender   = config('services.community_sms.sender');
        $this->url      = config('services.community_sms.url');
    }

    public function send($mobileNumber, $message)
    {
        // إذا كنا في بيئة التطوير المحلية، ما في داعي نخسر رصيد رسائل
        if (app()->environment('local')) {
            Log::info("SMS Simulated to {$mobileNumber}: {$message}");
            return true;
        }

        try {
            // الـ API يتطلب GUID فريد لكل رسالة (استخدمنا Str::uuid)
            $response = Http::post($this->url, [
                'UserName'    => $this->username, // [cite: 84]
                'Password'    => $this->password, // [cite: 85]
                'SMSText'     => $message,        // [cite: 86]
                'SMSLang'     => 'a',             // الـ OTP بالعربي
                'SMSSender'   => $this->sender,   // [cite: 88]
                'SMSReceiver' => $mobileNumber,   // [cite: 89]
                'SMSID'       => (string) Str::uuid(), // [cite: 90]
            ]);

            // حسب الـ document الرد الناجح بكون رقم 0 فقط
            if ($response->successful() && $response->body() == '0') { //
                return true;
            }
            // تسجيل الخطأ لو السيرفر رجع كود خطأ مثل -5 أو -1
            Log::error("Community SMS Failed. Response Code: " . $response->body() . " for numbers: " . $mobileNumber); // [cite: 435]
            return false;

        } catch (\Exception $e) {
            Log::error("Community SMS Exception: " . $e->getMessage());
            return false;
        }
    }
}