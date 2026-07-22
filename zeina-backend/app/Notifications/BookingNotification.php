<?php

namespace App\Notifications;

use App\Channels\FirebaseChannel; // القناة المخصصة تبعتنا
use App\Channels\SmsChannel;
use App\Service\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

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
        $this->title = $title;
        $this->body = $body;
        $this->bookingId = $bookingId;
        $this->extraData = $extraData;
    }

    /**
     * تحديد القنوات: التخزين بالداتابيز + الإرسال فوري فايربيز 🌟
     */
    public function via(object $notifiable): array
    {
        return ['database', FirebaseChannel::class, SmsChannel::class];
    }

    /**
     * 1. لوجيك الـ Database: كيف سيخزن الإشعار بجدول notifications للـ Inbox
     */
    public function toDatabase(object $notifiable): array
    {
        Log::info('--- [Database Channel] Preparing Data for Database Storage ---');

        return [
            'booking_id' => $this->bookingId,
            'title' => $this->title,
            'body' => $this->body,
            'extra_data' => $this->extraData,
        ];
    }

    /**
     * 2. لوجيك الـ Firebase: الداتا الممررة للموبايل كـ Push Notification
     */
    public function toFirebase(object $notifiable): array
    {

        Log::info('--- [Firebase Channel] Preparing Data for Firebase Push ---');

        $cleanExtraData = [];
        foreach ($this->extraData as $key => $value) {
            $cleanExtraData[$key] = is_array($value) ? json_encode($value) : (string) $value;
        }

        return [
            'title' => $this->title,
            'body' => $this->body,
            'extra_data' => array_merge($cleanExtraData, [
                'booking_id' => (string) $this->bookingId,
                'action_type' => 'open_booking_details',
            ]),
        ];
    }

    public function toSms(object $notifiable)
    {
        try {
            $smsService = app(SmsService::class);

            $titleKey = $this->title;
            $isCancelled = (str_contains(strtolower($titleKey), 'cancel') || str_contains($titleKey, 'ملغ') || str_contains($titleKey, 'إلغاء'));
            $isRescheduled = (str_contains(strtolower($titleKey), 'resched') || str_contains($titleKey, 'تعديل') || str_contains($titleKey, 'إعادة جدولة'));

            if (! empty($this->extraData['sms_message'])) {
                $smsMessage = $this->extraData['sms_message'];
            } elseif ($isCancelled) {
                $smsMessage = __('sms.booking_cancelled_sms', [
                    'ref' => $this->extraData['booking_ref'] ?? '',
                ]);
            } elseif ($isRescheduled) {
                $smsMessage = __('sms.booking_rescheduled_sms', [
                    'ref' => $this->extraData['booking_ref'] ?? '',
                    'date' => $this->extraData['booking_date'] ?? '',
                    'time' => $this->extraData['booking_time'] ?? '',
                ]);
            } else {
                $isCreatedOrConfirmed = str_contains(strtolower($titleKey), 'confirm') || str_contains(strtolower($titleKey), 'creat') || str_contains($titleKey, 'مؤكد') || str_contains($titleKey, 'إنشاء');

                if ($isCreatedOrConfirmed) {
                    $smsMessage = __('sms.booking_created_sms', [
                        'ref' => $this->extraData['booking_ref'] ?? '',
                        'date' => $this->extraData['booking_date'] ?? '',
                        'status' => 'Upcoming',
                        'workers_count' => $this->extraData['workers_count'] ?? 1,
                    ]);
                } else {
                    $smsMessage = $this->body;
                }
            }

            $bookingLink = '';
            if ($this->bookingId) {
                try {
                    $bookingLink = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                        'bookings.public_contract',
                        now()->addDays(30),
                        ['booking' => $this->bookingId]
                    );
                } catch (\Exception $urlEx) {
                    Log::error('Failed to generate signed URL for contract: ' . $urlEx->getMessage());
                }
            }

            if ($bookingLink) {
                $lang = $notifiable->preferred_language === 'ar' ? 'ar' : 'en';
                if ($lang === 'ar') {
                    $smsMessage .= "\nرابط العقد: " . $bookingLink;
                } else {
                    $smsMessage .= "\nContract link: " . $bookingLink;
                }
            }

            Log::info('[SMS Notification] Generated SMS Message Content: '.$smsMessage);
            $smsService->send($notifiable->phone_number, $smsMessage);

            Log::info('[SMS Notification] Success! SMS triggered via Service.');

        } catch (\Exception $e) {
            // تسجيل الخطأ في ملف الـ Log بدون تعطيل باقي الإشعارات
            Log::error('SMS Failed to send for user: '.$notifiable->phone_number.' | Error: '.$e->getMessage());
        }
    }
}
