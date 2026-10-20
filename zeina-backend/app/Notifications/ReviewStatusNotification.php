<?php

namespace App\Notifications;

use App\Channels\FirebaseChannel;
use App\Models\Review;
use App\Models\Service;
use App\Models\Worker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ReviewStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $title;
    public string $body;
    public int $reviewId;
    public ?int $bookingId;
    public ?string $bookingCode;
    public string $status;
    public ?string $reason;

    public function __construct(Review $review, string $status, ?string $reason = null)
    {
        $this->reviewId = $review->id;
        $this->bookingId = $review->booking_id;
        $this->bookingCode = $review->booking?->booking_code ?? ($review->booking_id ? "#{$review->booking_id}" : '');
        $this->status = $status;
        $this->reason = $reason;

        $targetName = '';
        if ($review->reviewable instanceof Service) {
            $targetName = $review->reviewable->name_ar ?: $review->reviewable->name_en;
        } elseif ($review->reviewable instanceof Worker) {
            $targetName = $review->reviewable->name;
        }

        if ($status === 'approved') {
            $this->title = 'تم اعتماد تقييمك بنجاح ⭐';
            if ($targetName && $this->bookingCode) {
                $this->body = "تمت الموافقة على تقييمك لـ ({$targetName}) في الحجز ({$this->bookingCode}). شكراً لمشاركتنا تجربتك!";
            } elseif ($this->bookingCode) {
                $this->body = "تمت الموافقة على تقييمك لطلب الحجز ({$this->bookingCode}). شكراً لمشاركتنا رأيك!";
            } else {
                $this->body = "تمت الموافقة على تقييمك ونشره بنجاح. شكراً لمشاركتنا رأيك!";
            }
        } else {
            $this->title = 'تعذر اعتماد تقييمك ⚠️';
            $bookingPart = $this->bookingCode ? " لطلب الحجز ({$this->bookingCode})" : '';
            $reasonPart = $reason ? " السبب: {$reason}." : ' لمخالفته معايير وشروط النشر المعتمدة.';
            $this->body = "نعتذر، تعذر اعتماد تقييمك{$bookingPart}{$reasonPart}";
        }
    }

    public function via(object $notifiable): array
    {
        return ['database', FirebaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        Log::info('--- [Review Status Notification] Storing in Database ---', [
            'review_id' => $this->reviewId,
            'status' => $this->status,
        ]);

        return [
            'title' => $this->title,
            'body' => $this->body,
            'review_id' => $this->reviewId,
            'booking_id' => $this->bookingId,
            'status' => $this->status,
            'reason' => $this->reason,
            'type' => 'review_status_update',
        ];
    }

    public function toFirebase(object $notifiable): array
    {
        Log::info('--- [Review Status Notification] Sending Push via Firebase ---', [
            'review_id' => $this->reviewId,
            'status' => $this->status,
        ]);

        return [
            'title' => $this->title,
            'body' => $this->body,
            'extra_data' => [
                'review_id' => (string) $this->reviewId,
                'booking_id' => (string) ($this->bookingId ?? ''),
                'status' => (string) $this->status,
                'reason' => (string) ($this->reason ?? ''),
                'action_type' => 'open_booking_details',
            ],
        ];
    }
}
