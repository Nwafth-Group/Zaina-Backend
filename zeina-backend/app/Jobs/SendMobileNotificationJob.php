<?php

namespace App\Jobs;

use App\Models\MobileNotification;
use App\Models\User;
use App\Service\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendMobileNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected int $notificationId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $notification = MobileNotification::find($this->notificationId);

        if (!$notification) {
            Log::warning("SendMobileNotificationJob: Notification ID {$this->notificationId} not found. Skipping.");
            return;
        }

        if ($notification->status !== 'scheduled') {
            Log::info("SendMobileNotificationJob: Notification ID {$this->notificationId} is status '{$notification->status}' (not 'scheduled'). Skipping.");
            return;
        }

        // Determine target recipients based on segment audience
        $userIds = [];
        if ($notification->audience === 'all') {
            $userIds = User::query()->where('type', 'customer')->pluck('id')->toArray();
        } elseif ($notification->audience === 'active_customers') {
            $userIds = User::query()->where('type', 'customer')->whereHas('bookings')->pluck('id')->toArray();
        } elseif ($notification->audience === 'inactive_customers') {
            $userIds = User::query()->where('type', 'customer')->whereDoesntHave('bookings')->pluck('id')->toArray();
        }

        $service = app(NotificationService::class);
        $sentCount = 0;

        foreach ($userIds as $userId) {
            // Check device platform matching targeted array (e.g. ios, android)
            $hasTargetDevice = DB::table('user_devices')
                ->where('user_id', $userId)
                ->whereIn('device_type', $notification->platforms)
                ->exists();

            if ($hasTargetDevice) {
                $user = User::find($userId);
                // Deliver message in preferred language if translations are available
                $title = $user->language === 'en' ? ($notification->title_en ?? $notification->title_ar) : ($notification->title_ar ?? $notification->title_en);
                $body = $user->language === 'en' ? ($notification->content_en ?? $notification->content_ar) : ($notification->content_ar ?? $notification->content_en);

                if ($title && $body) {
                    $sent = $service->sendPushNotification($userId, $title, $body);
                    if ($sent) {
                        $sentCount++;
                    }
                }
            }
        }

        // Complete transition status
        $notification->status = 'sent';
        $notification->save();

        Log::info("SendMobileNotificationJob: Notification ID {$this->notificationId} executed. Sent to {$sentCount} targeted customers.");
    }
}
