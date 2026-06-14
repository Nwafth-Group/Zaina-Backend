<?php

namespace App\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationService
{
    protected $messaging;
    // حقن محرك الـ Firebase Messaging تلقائياً عبر الـ Service Provider للـ Package
    public function __construct()
    {
        // حماية: نتحقق أن الملف والكونفيج مهيأ بالكامل لتجنب كراش السيرفر لو تعطل الفايربيز
        if (app()->has('firebase.messaging')) {
            $this->messaging = app('firebase.messaging');
        }
    }

    /**
     * إرسال إشعار لحظي (Push Notification) لمستخدم محدد بناءً على الـ Tokens الخاصة به
     */
    public function sendPushNotification(int $userId, string $title, string $body, array $extraData = [])
    {
        // 1. جلب جميع الـ FCM Tokens النشطة المرتبطة بهذا المستخدم من جدولنا
        $tokens = DB::table('user_devices')
            ->where('user_id', $userId)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            Log::info("Notification skipped for User ID: {$userId}. No registered device tokens found.");
            return false;
        }

        // 2. إذا لم تكن خدمة الفايربيز مهيأة بالـ .env للتطوير، نكتفي بكتابتها في الـ Log لتسهيل التيست
        if (!$this->messaging) {
            Log::warning("Firebase Messaging is not configured. Notification Logged: [Title: {$title} | Body: {$body}] for User ID: {$userId}");
            return true;
        }

        try {
            // 3. بناء هيكل الإشعار القياسي الذي يفهمه نظام الـ iOS والـ Android
            $notification = Notification::create($title, $body);

            // 4. إرسال الإشعار لكافة الأجهزة دفعة واحدة (Multicast Sending)
            // الـ extraData مفيدة جداً للموبايل عشان يفتح شاشة تفاصيل الحجز مباشرة عند النقر على الإشعار
            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData(array_merge($extraData, [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK', // مرجعية قياسية لنظام الموبايل
                    'sent_at'      => now()->toIso8601String()
                ]));

            // إرسال الإشعار لـ قائمة الـ Tokens المستهدفة
            $report = $this->messaging->sendMulticast($message, $tokens);

            // 5. هندسة ذكية وتنظيف تلقائي لقاعدة البيانات (Database Clean-up)
            // إذا أفاد الفايربيز أن هناك Token منتهي الصلاحية (مثلاً العميل مسح التطبيق)، نحذفه فوراً من جدولنا
            if ($report->hasFailures()) {
                foreach ($report->failures()->asArray() as $failure) {
                    // إذا كان الخطأ أن التوكن غير صالح أو منتهي
                    if ($failure->error()->getMessage() === 'The registration token is not a valid FCM registration token' || 
                        str_contains($failure->error()->getMessage(), 'not-found')) {
                        
                        $invalidToken = $failure->target()->value();
                        
                        DB::table('user_devices')->where('fcm_token', $invalidToken)->delete();
                        Log::info("Cleaned up expired FCM Token from database.");
                    }
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send Firebase Push Notification to User ID {$userId}: " . $e->getMessage());
            return false;
        }
    }
}