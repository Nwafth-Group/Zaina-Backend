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
}
