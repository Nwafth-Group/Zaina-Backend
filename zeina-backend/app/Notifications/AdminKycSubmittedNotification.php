<?php

namespace App\Notifications;

use App\Channels\FirebaseChannel;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class AdminKycSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $customer) {}

    public function via(object $notifiable): array
    {
        return ['database', FirebaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'طلب توثيق حساب جديد 📄',
            'body' => "قام العميل {$this->customer->full_name} ({$this->customer->phone_number}) بتقديم طلب توثيق الحساب وهو في انتظار المراجعة.",
            'customer_id' => $this->customer->id,
            'type' => 'kyc_submitted',
        ];
    }

    public function toFirebase(object $notifiable): array
    {
        return [
            'title' => 'طلب توثيق حساب جديد 📄',
            'body' => "قام العميل {$this->customer->full_name} ({$this->customer->phone_number}) بتقديم طلب توثيق الحساب وهو في انتظار المراجعة.",
            'extra_data' => [
                'customer_id' => (string) $this->customer->id,
                'action_type' => 'open_kyc_requests',
            ],
        ];
    }
}
