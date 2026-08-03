<?php

namespace App\Notifications;

use App\Channels\FirebaseChannel;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdminBookingEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $title;
    protected string $body;

    public function __construct(public string $event, public Booking $booking)
    {
        $ref = $booking->booking_ref;
        $customerName = $booking->customer ? $booking->customer->full_name : 'عميل';
        $serviceName = $booking->service ? (app()->getLocale() === 'en' ? $booking->service->name_en : $booking->service->name_ar) : 'خدمة منزلية';

        if ($event === 'created') {
            $this->title = "حجز جديد برقم {$ref} 📅";
            $this->body = "قام العميل {$customerName} بحجز خدمة ({$serviceName}) برقم مرجعي {$ref}.";
        } elseif ($event === 'completed') {
            $this->title = "إتمام حجز برقم {$ref} ✅";
            $this->body = "تم إتمام الخدمة بنجاح للحجز رقم {$ref} التابع للعميل {$customerName}.";
        } elseif ($event === 'cancelled') {
            $this->title = "إلغاء حجز برقم {$ref} ❌";
            $this->body = "تم إلغاء الحجز رقم {$ref} التابع للعميل {$customerName}.";
        } elseif ($event === 'rescheduled') {
            $this->title = "إعادة جدولة حجز برقم {$ref} 🔄";
            $this->body = "قام العميل {$customerName} بإعادة جدولة الحجز رقم {$ref}.";
        } elseif ($event === 'review') {
            $this->title = "تقييم جديد للحجز رقم {$ref} ⭐";
            $this->body = "قام العميل {$customerName} بتقديم تقييم للحجز رقم {$ref}.";
        } else {
            $this->title = "تحديث حجز برقم {$ref} ℹ️";
            $this->body = "تم تحديث حالة الحجز رقم {$ref}.";
        }
    }

    public function via(object $notifiable): array
    {
        return ['database', FirebaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'booking_ref' => $this->booking->booking_ref,
            'title' => $this->title,
            'body' => $this->body,
            'event' => $this->event,
            'type' => 'admin_booking_event',
        ];
    }

    public function toFirebase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'extra_data' => [
                'booking_id' => (string) $this->booking->id,
                'booking_ref' => (string) $this->booking->booking_ref,
                'event' => $this->event,
                'action_type' => 'open_booking_details',
            ],
        ];
    }
}
