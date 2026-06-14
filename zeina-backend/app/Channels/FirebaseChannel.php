<?php

namespace App\Channels;

use App\Service\NotificationService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FirebaseChannel
{
    protected $notificationService;

    // حقن الـ NotificationService المباشرة للفايربيز
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * إرسال الإشعار اللحظي عبر الفايربيز
     */
    public function send($notifiable, Notification $notification)
    {    
        Log::info("--- [Firebase Channel] Starting Custom Send Process ---");
        // نتحقق إذا كان ملف الـ Notification يحتوي على دالة toFirebase
        if (!method_exists($notification, 'toFirebase')) {
            return;
        }

        $data = $notification->toFirebase($notifiable);

        // إطلاق الـ Push Notification فوراً للأجهزة
        $this->notificationService->sendPushNotification(
            $notifiable->id,
            $data['title'],
            $data['body'],
            $data['extra_data'] ?? []
        );
    }
}