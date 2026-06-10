<?php

namespace App\Jobs;

use App\Service\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBookingNotificationSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 5;

    protected $mobile;
    protected $message;

    public function __construct(string $mobile, string $message)
    {
        $this->mobile = $mobile;
        $this->message = $message;
    }

    public function handle(SmsService $smsService): void
    {
        try {
            $smsSent = $smsService->send($this->mobile, $this->message);

            if (!$smsSent) {
                throw new \Exception("Failed to send Booking SMS Notification.");
            }
        } catch (\Exception $e) {
            Log::error("Queue Booking SMS Error: " . $e->getMessage());
            throw $e;
        }
    }
}