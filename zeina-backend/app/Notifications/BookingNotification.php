<?php

namespace App\Notifications;

use App\Channels\FirebaseChannel; // القناة المخصصة تبعتنا
use App\Service\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BookingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $title;
    protected $body;
    protected $bookingId;
    protected $extraData;

    /**
     * باني الإشعار يستقبل العنوان والنص والـ ID ديناميكياً لإعادة استخدامه في كل حالات الحجز
     */
    public function __construct($title, $body, $bookingId, array $extraData = [])
    {
        $this->title     = $title;
        $this->body      = $body;
        $this->bookingId = $bookingId;
        $this->extraData = $extraData;
    }

    /**
     * تحديد القنوات: التخزين بالداتابيز + الإرسال فوري فايربيز 🌟
     */
    public function via(object $notifiable): array
    {
        return ['database', FirebaseChannel::class , 'sms'];
    }

    /**
     * 1. لوجيك الـ Database: كيف سيخزن الإشعار بجدول notifications للـ Inbox
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'booking_id' => $this->bookingId,
            'title'      => $this->title,
            'body'       => $this->body,
            'extra_data' => $this->extraData,
        ];
    }

    /**
     * 2. لوجيك الـ Firebase: الداتا الممررة للموبايل كـ Push Notification
     */
    public function toFirebase(object $notifiable): array
    {
        return [
            'title'      => $this->title,
            'body'       => $this->body,
            'extra_data' => array_merge($this->extraData, [
                'booking_id'  => (string) $this->bookingId,
                'action_type' => 'open_booking_details'
            ]),
        ];
    }

    public function toSms(object $notifiable)
{
    $smsService = app(SmsService::class);
    $smsMessage = __('sms.booking_created_sms', [
        'ref'           => $this->extraData['booking_ref'] ?? '',
        'date'          => $this->extraData['booking_date'] ?? '',
        'status'        => 'Upcoming',
        'workers_count' => $this->extraData['workers_count'] ?? 1
    ]);

    $smsService->send($notifiable->phone_number, $smsMessage);
}
}