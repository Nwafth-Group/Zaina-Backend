<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminLoginRequest;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function login(AdminLoginRequest $request)
    {
        // 1. جلب المستخدم بناءً على الإيميل
        $user = User::where('email', $request->email)->first();

        // 2. التحقق من صحة الباسورد
        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse(__('messages.invalid_credentials'), 401);
        }

        // 3. حماية أمنية: التأكد أن المستخدم ليس عميلاً (يجب أن يكون Admin أو Operation)
        // بناءً على جدولك، الأنواع المسموح لها هي super_admin و operation
        if (in_array($user->type, ['customer', 'driver'])) {
            return $this->errorResponse(__('messages.unauthorized_admin_access'), 403);
        }

        // 4. التأكد أن الحساب فعال وغير موقوف
        if ($user->status !== 'active') {
            return $this->errorResponse(__('messages.account_inactive'), 403);
        }

        // 5. إصدار التوكن الخاص بـ Sanctum
        $token = $user->createToken('admin_token')->plainTextToken;

        // إرجاع النتيجة مع بيانات الأدمن والصلاحيات (مهمة جداً للـ Frontend ليعرف ماذا يعرض له)
        return $this->successResponse([
            'token' => $token,
            'admin' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'type' => $user->type,
                'roles' => $user->getRoleNames(), // جلب اسم الدور (مثل Super Admin)
                'permissions' => $user->getAllPermissions()->pluck('name'), // جلب الصلاحيات للوحة التحكم
            ]
        ], __('messages.login_success'));
    }

    public function logout(Request $request)
    {
        // حذف التوكن الحالي
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse(null, __('messages.logout_success'));
    }

}
