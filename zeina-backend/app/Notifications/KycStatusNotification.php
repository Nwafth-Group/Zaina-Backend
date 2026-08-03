<?php

namespace App\Notifications;

use App\Channels\FirebaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class KycStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $title;
    protected string $body;

    public function __construct(public string $status, public ?string $reason = null)
    {
        if ($status === 'approved') {
            $this->title = 'تم قبول توثيق حسابك بنجاح 🎉';
            $this->body = 'تهانينا! تمت الموافقة على توثيق حسابك رسمياً. يمكنك الآن الاستمتاع بكافة خدمات تطبيق زينة.';
        } else {
            $this->title = 'تم رفض طلب توثيق الحساب ⚠️';
            $reasonText = $reason ? " السبب: {$reason}." : '';
            $this->body = "تعذر قبول طلب توثيق حسابك.{$reasonText} يُرجى تحديث المستندات المطلوبة وإعادة المحاولة.";
        }
    }

    public function via(object $notifiable): array
    {
        return ['database', FirebaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'reason' => $this->reason,
            'type' => 'kyc_status_update',
        ];
    }

    public function toFirebase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'extra_data' => [
                'status' => $this->status,
                'reason' => (string) ($this->reason ?? ''),
                'action_type' => 'open_kyc_status',
            ],
        ];
    }
}
