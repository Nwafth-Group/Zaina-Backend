<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProfileRequest;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service profile management for the currently authenticated admin.
 */
class ProfileController extends Controller
{
    use ApiResponseTrait;

    public function show(Request $request)
    {
        return $this->successResponse(new UserResource($request->user()->load('roles')));
    }

    /**
     * The authenticated admin's live identity, roles and permissions — same
     * shape as the login payload. The frontend re-fetches this on load so the
     * UI (sidebar links, action buttons) reflects the user's current
     * permissions instead of a stale login-time snapshot.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->full_name,
            'email' => $user->email,
            'type' => $user->type,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->successResponse(new UserResource($user->load('roles')), __('messages.profile_updated'));
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = $request->user();
        $user->password = Hash::make($request->validated('password'));
        $user->save();

        return $this->successResponse(null, __('messages.password_changed'));
    }

    public function notifications(Request $request)
    {
        $user = $request->user();
        // Return latest 20 notifications (read & unread) for this admin
        $notifications = $user->notifications()->latest()->limit(20)->get();

        return $this->successResponse($notifications);
    }

    public function markNotificationsAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->successResponse(null, __('messages.notifications_marked_read') ?? 'Marked as read');
    }
}
