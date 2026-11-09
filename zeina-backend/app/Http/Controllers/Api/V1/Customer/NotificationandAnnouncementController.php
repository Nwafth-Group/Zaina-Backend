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

            $formatted = collect($notifications->items())->map(function ($n) {
                return [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? '',
                    'body' => $n->data['body'] ?? '',
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
}
