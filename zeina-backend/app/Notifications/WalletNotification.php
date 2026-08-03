<?php

namespace App\Notifications;

use App\Channels\FirebaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WalletNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public float $amount,
        public string $type = 'credit', // 'credit', 'debit', 'refund'
        public array $extraData = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FirebaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'amount' => $this->amount,
            'transaction_type' => $this->type,
            'extra_data' => $this->extraData,
        ];
    }

    public function toFirebase(object $notifiable): array
    {
        $cleanExtraData = [];
        foreach ($this->extraData as $key => $value) {
            $cleanExtraData[$key] = is_array($value) ? json_encode($value) : (string) $value;
        }

        return [
            'title' => $this->title,
            'body' => $this->body,
            'extra_data' => array_merge($cleanExtraData, [
                'amount' => (string) $this->amount,
                'transaction_type' => $this->type,
                'action_type' => 'open_wallet',
            ]),
        ];
    }
}
