<?php

namespace App\Jobs;

use App\Service\SmsService; // ⚠️ تأكد أن هذا المسار يطابق تماماً مجلد السيرفس الحقيقي عندك
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOtpSmsJob implements ShouldQueue
{
    use Queueable;

    protected $mobile;
    protected $message; 

    public $tries = 3;   
    public $backoff = 5;

    public function __construct(string $mobile, string $message)
    {
        $this->mobile = $mobile;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(SmsService $smsService): void
{      
    try {
        $cleanMobile = preg_replace('/[^0-9]/', '', $this->mobile);
        $smsSent = $smsService->send($cleanMobile, $this->message);

        if (!$smsSent) {
            // نكتفي بكتابة تحذير بالـ Log عشان نعرف إن البوابة رفضت الرقم، بدون ما نموت الجوب
            Log::warning("SMS Gateway returned failure response for number: {$this->mobile}");
        } else {
            Log::info("SMS Sent successfully to: {$this->mobile}");
        }
        
    } catch (\Exception $e) {
        // طباعة الاستثناء لحصر أخطاء السيرفر (مثل انقطاع الاتصال بالـ API)
        Log::error("Queue SMS Handle Exception safely caught: " . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        // 🌟 السر هنا: حذفنا سطر throw $e; 🌟
        // كذا الجوب بتخلص شغلها بسلام كـ Success، وبتمرر الدور فوراً لـ جوب الفايربيز اللي بعدها في الطابور!
    }
}

    /**
     * دالة الفشل النهائي بعد استنفاد الـ 3 محاولات
     */
    public function failed(Throwable $exception): void
    {
        // 🔥 هون السحر! الـ $exception بيمسك السبب الحقيقي بالظبط (سواء كود خطأ أو مشكلة كلاس)
        Log::critical("⚠️ فشل نهائي في إرسال رسالة الـ OTP للرقم: {$this->mobile}.", [
            'reason' => $exception->getMessage(),
            'file'   => $exception->getFile(),
            'line'   => $exception->getLine(),
            'trace'  => $exception->getTraceAsString() // بيطبعلك شجرة الخطأ كاملة بالـ log
        ]);
    }
}