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
            $smsSent = $smsService->send($this->mobile, $this->message);

            if (!$smsSent) {
                // هان بنرمي الـ Exception المخصص لو الـ API رجع كود غير الـ 0
                throw new \Exception("Failed to send SMS via Community Ads API.");
            }
        } catch (\Exception $e) {
            // 🔥 تعديل ذهبي: بنطبع الـ Exception نفسه مع الـ Stack Trace بالـ log الأساسي
            Log::error("Queue SMS Handle Exception: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e; 
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