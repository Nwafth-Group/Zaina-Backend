<?php

namespace App\Notifications;

use App\Channels\FirebaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class GeneralPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $title;
    protected $body;
    protected $mobileNotificationId;
    protected $extraData;

    public function __construct(string $title, string $body, int $mobileNotificationId, array $extraData = [])
    {
        $this->title = $title;
        $this->body = $body;
        $this->mobileNotificationId = $mobileNotificationId;
        $this->extraData = $extraData;
    }

    public function via(object $notifiable): array
    {
        return ['database', FirebaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        Log::info('--- [General Notification] Storing in Database ---');

        return [
            'title' => $this->title,
            'body' => $this->body,
            'mobile_notification_id' => $this->mobileNotificationId,
            'extra_data' => $this->extraData,
        ];
    }

    public function toFirebase(object $notifiable): array
    {
        Log::info('--- [General Notification] Preparing Firebase Push ---');

        $cleanExtraData = [];
        foreach ($this->extraData as $key => $value) {
            $cleanExtraData[$key] = is_array($value) ? json_encode($value) : (string) $value;
        }

        return [
            'title' => $this->title,
            'body' => $this->body,
            'extra_data' => array_merge($cleanExtraData, [
                'mobile_notification_id' => (string) $this->mobileNotificationId,
                'action_type' => 'open_general_notification',
            ]),
        ];
    }
}
