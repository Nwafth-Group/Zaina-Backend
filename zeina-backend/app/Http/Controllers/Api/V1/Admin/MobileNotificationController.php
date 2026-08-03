<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreMobileNotificationRequest;
use App\Http\Requests\Api\V1\Admin\UpdateMobileNotificationRequest;
use App\Http\Resources\Api\V1\Notification\MobileNotificationResource;
use App\Models\MobileNotification;
use App\Jobs\SendMobileNotificationJob;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileNotificationController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of mobile notifications.
     */
    public function index(Request $request)
    {
        $query = MobileNotification::query()->with(['creator', 'updater']);

        // Search filter
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->input('search') . '%');
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Audience filter
        if ($request->filled('audience')) {
            $query->where('audience', $request->input('audience'));
        }

        // Sorting
        $allowedSorts = ['send_date', 'send_time', 'status', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'created_at';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'send_date') {
            $query->orderBy('send_date', $sortDir)->orderBy('send_time', $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $counts = [
            'all' => MobileNotification::count(),
            'scheduled' => MobileNotification::where('status', 'scheduled')->count(),
            'sent' => MobileNotification::where('status', 'sent')->count(),
            'failed' => MobileNotification::where('status', 'failed')->count(),
        ];

        $perPage = $request->integer('per_page', 15);
        $notifications = $query->paginate($perPage);

        $itemsResource = MobileNotificationResource::collection($notifications);
        $meta = [
            'total' => $notifications->total(),
            'per_page' => $notifications->perPage(),
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
        ];

        return $this->successResponse([
            'items' => $itemsResource,
            'counts' => $counts,
            'meta' => $meta,
        ], __('messages.notification_list_fetch_success'));
    }

    /**
     * Store a newly created mobile notification and schedule the queue job.
     */
    public function store(StoreMobileNotificationRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id() ?: auth('sanctum')->id() ?: optional(request()->user())->id;
        $data['status'] = 'scheduled';

        $notification = MobileNotification::create($data);

        // Schedule delayed push delivery job
        $scheduledAt = \Carbon\Carbon::parse($notification->send_date . ' ' . $notification->send_time);
        $delaySeconds = max(0, now()->diffInSeconds($scheduledAt, false));

        SendMobileNotificationJob::dispatch($notification->id)->delay($delaySeconds);

        Log::info("Mobile Notification scheduled: ID {$notification->id} for {$scheduledAt->toDateTimeString()} by user ID " . ($data['created_by'] ?? 'guest'));

        return $this->successResponse(
            new MobileNotificationResource($notification->fresh(['creator'])),
            __('messages.notification_create_success'),
            201
        );
    }

    /**
     * Display the specified mobile notification.
     */
    public function show(MobileNotification $mobileNotification)
    {
        return $this->successResponse(
            new MobileNotificationResource($mobileNotification->load(['creator', 'updater'])),
            __('messages.notification_show_success')
        );
    }

    /**
     * Update the scheduled mobile notification.
     */
    public function update(UpdateMobileNotificationRequest $request, MobileNotification $mobileNotification)
    {
        if ($mobileNotification->status !== 'scheduled') {
            return $this->errorResponse(__('messages.notification_edit_forbidden'), 422);
        }

        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        $dateOrTimeChanged = ($mobileNotification->send_date !== $data['send_date']) || ($mobileNotification->send_time !== $data['send_time']);

        $mobileNotification->update($data);

        // Re-dispatch if scheduled delivery timestamp was modified
        if ($dateOrTimeChanged) {
            $scheduledAt = \Carbon\Carbon::parse($mobileNotification->send_date . ' ' . $mobileNotification->send_time);
            $delaySeconds = max(0, now()->diffInSeconds($scheduledAt, false));

            SendMobileNotificationJob::dispatch($mobileNotification->id)->delay($delaySeconds);
            
            Log::info("Mobile Notification rescheduled: ID {$mobileNotification->id} to {$scheduledAt->toDateTimeString()} by user ID " . auth()->id());
        } else {
            Log::info("Mobile Notification fields updated: ID {$mobileNotification->id} by user ID " . auth()->id());
        }

        return $this->successResponse(
            new MobileNotificationResource($mobileNotification),
            __('messages.notification_update_success')
        );
    }

    /**
     * Remove the specified mobile notification from storage (subject to status rules).
     */
    public function destroy(MobileNotification $mobileNotification)
    {
        if ($mobileNotification->status === 'sent') {
            return $this->errorResponse(__('messages.notification_delete_forbidden'), 422);
        }

        $notificationId = $mobileNotification->id;
        $mobileNotification->delete();

        Log::info("Mobile Notification deleted: ID {$notificationId} by user ID " . auth()->id());

        return $this->successResponse(null, __('messages.notification_delete_success'));
    }

    /**
     * Cancel a scheduled mobile notification.
     */
    public function cancel(MobileNotification $mobileNotification)
    {
        if ($mobileNotification->status !== 'scheduled') {
            return $this->errorResponse(__('messages.notification_cancel_forbidden'), 422);
        }

        $mobileNotification->status = 'cancelled';
        $mobileNotification->updated_by = auth()->id();
        $mobileNotification->save();

        Log::info("Mobile Notification cancelled: ID {$mobileNotification->id} by user ID " . auth()->id());

        return $this->successResponse(
            new MobileNotificationResource($mobileNotification),
            __('messages.notification_cancel_success')
        );
    }

    /**
     * Send a mobile notification immediately (Send Now).
     */
    public function sendNow(MobileNotification $mobileNotification)
    {
        if ($mobileNotification->status === 'sent') {
            return $this->errorResponse(
                app()->getLocale() === 'ar' 
                    ? 'تم إرسال هذا الإشعار بالفعل.' 
                    : 'This notification has already been sent.', 
                422
            );
        }

        $userId = auth()->id() ?: auth('sanctum')->id() ?: optional(request()->user())->id;

        $mobileNotification->status = 'scheduled';
        $mobileNotification->send_date = now()->toDateString();
        $mobileNotification->send_time = now()->toTimeString();
        $mobileNotification->updated_by = $userId;
        $mobileNotification->save();

        // إرسال الإشعار فورياً ومزامنة الحالة
        SendMobileNotificationJob::dispatchSync($mobileNotification->id);

        $mobileNotification->refresh()->load(['creator', 'updater']);

        Log::info("Mobile Notification sent immediately (send-now): ID {$mobileNotification->id} by user ID " . ($userId ?? 'guest'));

        return $this->successResponse(
            new MobileNotificationResource($mobileNotification),
            app()->getLocale() === 'ar' ? 'تم إرسال الإشعار بنجاح.' : 'Notification sent successfully.'
        );
    }
}
