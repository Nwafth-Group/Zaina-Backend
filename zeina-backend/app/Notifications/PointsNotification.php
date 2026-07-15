<?php

namespace App\Notifications;

use App\Channels\FirebaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class PointsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $title;
    protected $body;
    protected $pointsAmount;
    protected $type; // 'earn', 'redeem', 'refund', 'expire'
    protected $extraData;

    public function __construct(string $title, string $body, int $pointsAmount, string $type = 'earn', array $extraData = [])
    {
        $this->title = $title;
        $this->body = $body;
        $this->pointsAmount = $pointsAmount;
        $this->type = $type;
        $this->extraData = $extraData;
    }

    public function via(object $notifiable): array
    {
        return ['database', FirebaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        Log::info('--- [Points Notification] Storing in Database ---');

        return [
            'title' => $this->title,
            'body' => $this->body,
            'points_amount' => $this->pointsAmount,
            'points_type' => $this->type,
            'extra_data' => $this->extraData,
        ];
    }

    public function toFirebase(object $notifiable): array
    {
        Log::info('--- [Points Notification] Preparing Firebase Push ---');

        $cleanExtraData = [];
        foreach ($this->extraData as $key => $value) {
            $cleanExtraData[$key] = is_array($value) ? json_encode($value) : (string) $value;
        }

        return [
            'title' => $this->title,
            'body' => $this->body,
            'extra_data' => array_merge($cleanExtraData, [
                'points_amount' => (string) $this->pointsAmount,
                'points_type' => $this->type,
                'action_type' => 'open_points_wallet',
            ]),
        ];
    }
}
