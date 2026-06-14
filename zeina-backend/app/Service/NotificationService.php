<?php

namespace App\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationService
{
    protected $messaging;

    public function __construct()
    {
        if (app()->has('firebase.messaging')) {
            $this->messaging = app('firebase.messaging');
        }
    }

    /**
     * إرسال إشعار لحظي (Push Notification) لمستخدم محدد بناءً على الـ Tokens الخاصة به
     */
    public function sendPushNotification(int $userId, string $title, string $body, array $extraData = [])
    {
        Log::info("Attempting to send Firebase Push Notification in Notification Service to User ID: {$userId} with Title: '{$title}'"); 
        Log::info("Fetching FCM Tokens for User ID: {$userId} from database...");
        
        $tokens = DB::table('user_devices')
            ->where('user_id', $userId)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            Log::info("Notification skipped for User ID: {$userId}. No registered device tokens found.");
            return false;
        }

        if (!$this->messaging) {
            Log::warning("Firebase Messaging is not configured. Notification Logged: [Title: {$title} | Body: {$body}] for User ID: {$userId}");
            return true;
        }

        try {
            Log::info("Building Firebase Cloud Message for User ID: {$userId}...");
            $notification = Notification::create($title, $body);

            // 🌟 الحل الجذري والعبقري: تنظيف وتأمين مصفوفة البيانات الإضافية 🌟
            // نقوم بعمل تحويل قسري (String Casting) لكافة القيم بداخل المصفوفة لإرضاء معايير جوجل الصارمة
            $cleanExtraData = [];
            foreach ($extraData as $key => $value) {
                // لو كانت القيمة مصفوفة (Array) نحولها لـ JSON String، غير ذلك نحولها لـ String عادي
                $cleanExtraData[$key] = is_array($value) ? json_encode($value) : (string) $value;
            }

            Log::info("Preparing Firebase Cloud Message for User ID: {$userId}...");
            
            // دمج المصفوفة النظيفة مع بارامترات النظام الإلزامية ونوعها نصي أيضاً
            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData(array_merge($cleanExtraData, [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sent_at'      => now()->toIso8601String()
                ]));

            Log::info("Sending Firebase Push Notification to User ID: {$userId}...");
            $report = $this->messaging->sendMulticast($message, $tokens);

            // 5. هندسة ذكية وتنظيف تلقائي لقاعدة البيانات (Database Clean-up)
            if ($report->hasFailures()) {
                foreach ($report->failures()->asArray() as $failure) {
                    if ($failure->error()->getMessage() === 'The registration token is not a valid FCM registration token' || 
                        str_contains($failure->error()->getMessage(), 'not-found')) {
                        
                        Log::info("Found invalid FCM Token for User ID: {$userId}");
                        $invalidToken = $failure->target()->value();
                        
                        DB::table('user_devices')->where('fcm_token', $invalidToken)->delete();
                        Log::info("Cleaned up expired FCM Token from database.");
                    }
                }
            }
            
            Log::info("Successfully sent Firebase Push Notification to User ID: {$userId}");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send Firebase Push Notification to User ID {$userId}: " . $e->getMessage());
            return false;
        }
    }
}