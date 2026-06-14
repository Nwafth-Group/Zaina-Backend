<?php 
namespace App\Channels;

use Illuminate\Notifications\Notification;
use App\Service\SmsService;

class SmsChannel
{
    public function send(object $notifiable, Notification $notification)
    {
        // نستدعي دالة toSms اللي إنت كاتبها جوا كلاس الإشعار
        if (method_exists($notification, 'toSms')) {
            $notification->toSms($notifiable);
        }
    }
}