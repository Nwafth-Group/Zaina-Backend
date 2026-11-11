<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Announcement\AnnouncementCustomerResource;
use App\Models\Announcement;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class NotificationandAnnouncementController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $user = auth()->user();
        $type = $request->get('type', 'notification'); // القيمة الافتراضية للـ Tab الأول
        $perPage = $request->get('per_page', 15);

        // حساب عدد غير المقروء للإشعارات والإعلانات
        $unreadNotifications = $user->unreadNotifications()->count();
        $readAnnouncementIds = $user->readAnnouncements()
            ->wherePivotNotNull('read_at')
            ->pluck('announcements.id')
            ->toArray();
        $unreadAnnouncements = Announcement::whereNotIn('id', $readAnnouncementIds)->count();
        $totalUnread = $unreadNotifications + $unreadAnnouncements;

        $params = [
            'unread_count' => $totalUnread,
            'notifications_unread_count' => $unreadNotifications,
            'announcements_unread_count' => $unreadAnnouncements,
            'has_unread' => $totalUnread > 0,
        ];

        if ($type === 'notification') {
            $notifications = $user->notifications()->paginate($perPage);

            $locale = app()->getLocale();

            $formatted = collect($notifications->items())->map(function ($n) use ($locale) {
                $localized = $this->localizeNotification($n->data ?? [], $locale);

                return [
                    'id' => $n->id,
                    'title' => $localized['title'],
                    'body' => $localized['body'],
                    'booking_id' => $n->data['booking_id'] ?? null,
                    'is_read' => ! is_null($n->read_at),
                    'read_at' => $n->read_at ? $n->read_at->toIso8601String() : null,
                    'created_at' => $n->created_at->diffForHumans(), // مريحة جداً للموبايل مثل "4m" أو "1d"
                ];
            });

            return $this->successResponse($formatted, __('messages.notification_fetch_success'), 200, $params);
        }
        // 🔴 القسم الثاني: الإعلانات العامة للآدمن (Announcement Tab)
        if ($type === 'announcement') {
            // جلب الإعلانات من الأحدث للأقدم
            $announcements = Announcement::orderBy('created_at', 'desc')->paginate($perPage);
            // جلب الـ IDs للإعلانات التي قرأها هذا المستخدم سابقاً لتوفير الاستعلامات بداخل الـ Loop

            return $this->successResponse(AnnouncementCustomerResource::collection($announcements->items()), __('messages.announ_fetch_success'), 200, $params);

        }
    }

    /**
     * جلب عدد الإشعارات والإعلانات غير المقروءة للعميل (Dedicated Unread Count Endpoint)
     */
    public function unreadCount(Request $request)
    {
        $user = auth()->user();

        $unreadNotifications = $user->unreadNotifications()->count();

        $readAnnouncementIds = $user->readAnnouncements()
            ->wherePivotNotNull('read_at')
            ->pluck('announcements.id')
            ->toArray();
        $unreadAnnouncements = Announcement::whereNotIn('id', $readAnnouncementIds)->count();

        $totalUnread = $unreadNotifications + $unreadAnnouncements;

        return $this->successResponse([
            'unread_count' => $totalUnread,
            'notifications_unread_count' => $unreadNotifications,
            'announcements_unread_count' => $unreadAnnouncements,
            'has_unread' => $totalUnread > 0,
        ], __('messages.unread_count_fetch_success'));
    }

    /**
     * 2. تحديد عنصر كمقروء (Mark As Read)
     * يدعم إما ID الإشعار المخصص أو ID الإعلان العام بناءً على الـ type
     */
    public function markAsRead(Request $request, $id)
    {

        $user = auth()->user();
        $type = $request->get('type', 'notification');

        if ($type === 'notification') {
            $notification = $user->notifications()->where('id', $id)->first();
            if (! $notification) {
                return $this->errorResponse(__('messages.notification_not_found'), 404);
            }
            $notification->markAsRead();

            return $this->successResponse(null, __('messages.notification_read_success'));
        }

        if ($type === 'announcement') {
            $announcement = Announcement::find($id);
            if (! $announcement) {
                return $this->errorResponse(__('messages.annouun_not_exist'), 404);
            }

            // تسجيل القراءة في الـ Pivot table دون تكرار باستخدام syncWithoutDetaching
            $user->readAnnouncements()->syncWithoutDetaching([
                $id => ['read_at' => now()],
            ]);

            return $this->successResponse(__('messages.announcemnt_mark_read'));
        }
    }

    /**
     * 3. قراءة الكل بضغطة واحدة (Mark All As Read)
     */
    public function markAllAsRead(Request $request)
    {
        $user = auth()->user();
        $type = $request->get('type', 'notification');

        if ($type === 'notification') {
            $user->unreadNotifications->markAsRead();

            return $this->successResponse(null, __('messages.notification_read_success'));
        }

        if ($type === 'announcement') {
            // 1. جلب الـ IDs الحقيقية للإعلانات الموجودة في الجدول حالياً
            $allAnnouncementIds = Announcement::pluck('id')->toArray();

            // 2. بناء مصفوفة المزامنة
            $syncData = [];
            foreach ($allAnnouncementIds as $announcementId) {
                $syncData[(int) $announcementId] = [
                    'read_at' => now(),
                ];
            }

            // 3. التنفيذ
            if (! empty($syncData)) {
                $user->readAnnouncements()->syncWithoutDetaching($syncData);
            }

            return $this->successResponse(null, __('messages.all_announcemnt_mark_read') ?? 'All announcements marked as read successfully.');
        }
    }

    /**
     * دالة فرعية لتوليد الـ الـ Meta بانتظام لمنع تكرار الكود
     */
    private function getMetaStructure($paginator, $hasUnread)
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'has_unread' => $hasUnread, // 🔴 النقطة الحمراء بالـ UI بتضوي بناءً على هذا الـ Boolean
        ];
    }

    /**
     * Localize notification title and body according to current locale (ar/en).
     */
    private function localizeNotification(array $data, string $locale): array
    {
        $title = $data['title'] ?? '';
        $body = $data['body'] ?? '';

        if ($locale === 'ar') {
            if (! empty($data['title_ar'])) {
                $title = $data['title_ar'];
            }
            if (! empty($data['body_ar'])) {
                $body = $data['body_ar'];
            }

            // Translate English titles to Arabic
            if (preg_match('/new points earned/i', $title)) {
                $title = 'حصلت على نقاط جديدة! 🎉';
            } elseif (preg_match('/booking cancelled/i', $title)) {
                $title = 'تم إلغاء الحجز';
            } elseif (preg_match('/booking confirmed/i', $title)) {
                $title = 'تم تأكيد الحجز! 🎉';
            } elseif (preg_match('/booking rescheduled/i', $title)) {
                $title = 'تمت إعادة جدولة الحجز! 🔄';
            } elseif (preg_match('/reminder.*(?:1 hour|one hour)/i', $title)) {
                $title = 'تذكير: موعد خدمتك بعد ساعة واحدة ⏰';
            } elseif (preg_match('/service provider is on the way/i', $title)) {
                $title = 'مقدم الخدمة في الطريق! 🚗';
            } elseif (preg_match('/service started/i', $title)) {
                $title = 'بدأت الخدمة';
            } elseif (preg_match('/service completed/i', $title)) {
                $title = 'اكتملت الخدمة';
            } elseif (preg_match('/workers assignment updated/i', $title)) {
                $title = 'تحديث تعيين العاملات';
            } elseif (preg_match('/review approved/i', $title)) {
                $title = 'تم اعتماد تقييمك';
            } elseif (preg_match('/review rejected/i', $title)) {
                $title = 'تم رفض تقييمك';
            } elseif (preg_match('/kyc approved|account verified/i', $title)) {
                $title = 'تم توثيق الحساب بنجاح';
            } elseif (preg_match('/kyc rejected/i', $title)) {
                $title = 'تم رفض توثيق الحساب';
            }

            // Translate English bodies to Arabic
            if (preg_match('/(\d+)\s+points have been added to your balance for completing your service/i', $body, $m)) {
                $body = "تمت إضافة {$m[1]} نقطة إلى رصيدك لإتمامك الخدمة بنجاح.";
            } elseif (preg_match('/thank you for your rating.*extra\s+(\d+)\s+points/i', $body, $m)) {
                $body = "شكرًا لتقييمك! تمت إضافة {$m[1]} نقطة إضافية إلى محفظتك.";
            } elseif (preg_match('/your booking\s+([A-Za-z0-9-]+)\s+has been cancelled by admin/i', $body, $m)) {
                $body = "تم إلغاء حجزك رقم {$m[1]} بواسطة الإدارة.";
            } elseif (preg_match('/your booking with reference number\s+([A-Za-z0-9-]+)\s+is now officially confirmed/i', $body, $m)) {
                $body = "حجزك برقم {$m[1]} تم تأكيده رسميًا ومجدول الآن بنجاح.";
            } elseif (preg_match('/your booking\s+([A-Za-z0-9-]+)\s+has been rescheduled to\s+(.*?)\s+at\s+(.*?)(?:\.|$)/i', $body, $m)) {
                $body = "حجزك برقم {$m[1]} تم إعادة جدولته إلى {$m[2]} الساعة {$m[3]}.";
            } elseif (preg_match('/your service for booking ref:\s*([A-Za-z0-9-]+)\s*is now in service/i', $body, $m)) {
                $body = "حجزك رقم {$m[1]} هو الآن قيد التنفيذ.";
            } elseif (preg_match('/your service for booking ref:\s*([A-Za-z0-9-]+)\s*has been completed/i', $body, $m)) {
                $body = "تم إتمام خدمتك للحجز رقم {$m[1]} بنجاح. شكراً لك!";
            } elseif (preg_match('/assigned workers for your booking\s+([A-Za-z0-9-]+)\s+have been updated/i', $body, $m)) {
                $body = "تم تحديث تعيين العاملات لحجزك رقم {$m[1]}.";
            }
        } elseif ($locale === 'en') {
            if (! empty($data['title_en'])) {
                $title = $data['title_en'];
            }
            if (! empty($data['body_en'])) {
                $body = $data['body_en'];
            }

            // Translate Arabic titles to English
            if (str_contains($title, 'نقاط جديدة')) {
                $title = 'New Points Earned! 🎉';
            } elseif (str_contains($title, 'إلغاء الحجز')) {
                $title = 'Booking Cancelled';
            } elseif (str_contains($title, 'تأكيد الحجز')) {
                $title = 'Booking Confirmed! 🎉';
            } elseif (str_contains($title, 'إعادة جدولة')) {
                $title = 'Booking Rescheduled! 🔄';
            } elseif (str_contains($title, 'استرداد المبلغ') || str_contains($title, 'استرجاع')) {
                $title = 'Amount Refunded to Wallet 💰';
            } elseif (str_contains($title, 'بدأت الخدمة')) {
                $title = 'Service Started';
            } elseif (str_contains($title, 'اكتملت الخدمة')) {
                $title = 'Service Completed';
            } elseif (str_contains($title, 'تعيين العاملات')) {
                $title = 'Workers Assignment Updated';
            }

            // Translate Arabic bodies to English
            if (preg_match('/تمت إضافة\s+(\d+)\s+نقطة إلى رصيدك/u', $body, $m)) {
                $body = "{$m[1]} points have been added to your balance for completing your service.";
            } elseif (preg_match('/تم إلغاء حجزك رقم\s+([A-Za-z0-9-]+)\s+بواسطة الإدارة/u', $body, $m)) {
                $body = "Your booking {$m[1]} has been cancelled by Admin.";
            } elseif (preg_match('/حجزك برقم\s+([A-Za-z0-9-]+)\s+تم تأكيده رسميًا/u', $body, $m)) {
                $body = "Your booking with reference number {$m[1]} is now officially confirmed and scheduled successfully.";
            } elseif (preg_match('/تم استرداد مبلغ\s+([\d\.]+)\s*(?:ج\.م|ر\.س)?\s*للحجز رقم\s+([A-Za-z0-9-]+)\s*إلى محفظتك/u', $body, $m)) {
                $body = "An amount of {$m[1]} EGP for booking {$m[2]} has been refunded to your wallet.";
            } elseif (preg_match('/حجزك رقم\s+([A-Za-z0-9-]+)\s+هو الآن قيد التنفيذ/u', $body, $m)) {
                $body = "Your service for booking ref: {$m[1]} is now in service.";
            } elseif (preg_match('/تم إتمام خدمتك للحجز رقم\s+([A-Za-z0-9-]+)\s+بنجاح/u', $body, $m)) {
                $body = "Your service for booking ref: {$m[1]} has been completed. Thank you!";
            } elseif (preg_match('/تم تحديث تعيين العاملات لحجزك رقم\s+([A-Za-z0-9-]+)/u', $body, $m)) {
                $body = "The assigned workers for your booking {$m[1]} have been updated.";
            }
        }

        return ['title' => $title, 'body' => $body];
    }
}
