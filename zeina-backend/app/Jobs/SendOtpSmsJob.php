<?php

namespace App\Jobs;

use App\Service\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SendOtpSmsJob implements ShouldQueue
{
    use Queueable;

   protected $mobile;
   protected $message; 

    public $tries = 3;   
    public $backoff = 5;

    // المبرمج بيمرر البيانات هان لما يستدعي الـ Job
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
            throw new \Exception("Failed to send SMS via Community Ads API.");
        }
    } catch (\Exception $e) {
        Log::error("Queue SMS Error: " . $e->getMessage());
        throw $e; 
    }
}

public function failed(Throwable $exception): void
{
    // 1. تسجيل تنبيه خطير في الـ Logs
    Log::critical("⚠️ فشل نهائي في إرسال رسالة الـ OTP للرقم: {$this->mobile}. السبب: " . $exception->getMessage());

    // 2. [إختياري] يمكنك هنا عمل أكشن مخصص:
}

}
