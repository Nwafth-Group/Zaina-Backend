<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponseTrait;
    public function index(Request $request)
    {    
        // التحقق من الصلاحيات (BR-03)
        
        // abort_if(!auth()->user()->can('users.view'), 403, 'Unauthorized');
        $query = User::with('roles'); 
        // 1. البحث (Name, Phone, Email, User ID)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id_number', $search)
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        // 2. الفلاتر (User Type, Status, Role, Created Date)
        $query->when($request->input('type'), function ($q, $type) {
            $q->where('type', $type);
        });

        $query->when($request->input('status'), function ($q, $status) {
            $q->where('status', $status);
        });

        $query->when($request->input('role'), function ($q, $role) {
            $q->whereHas('roles', function ($r) use ($role) {
                $r->where('name', $role);
            });
        });

        $query->when($request->input('date_from'), function ($q, $dateFrom) {
            $q->whereDate('created_at', '>=', $dateFrom);
        });

        $query->when($request->input('date_to'), function ($q, $dateTo) {
            $q->whereDate('created_at', '<=', $dateTo);
        });

        // 3. الترتيب (Sort by CreatedAt, Status, User Type)
        $sortBy = $request->input('sort_by', 'created_at'); // الافتراضي
        $sortDir = $request->input('sort_dir', 'desc');
        
        // التأكد من أن الأعمدة المسموح الترتيب بها آمنة
        $allowedSorts = ['created_at', 'status', 'type', 'id'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        }

        // 4. التقسيم (Pagination)
        $perPage = $request->input('per_page', 15);
        $users = $query->paginate($perPage);

        // إرجاع النتيجة منسقة عبر الـ Resource
        return $this->successResponse(UserResource::collection($users), __('messages.users_retrieved'));
        // return UserResource::collection($users);
    }

    public function show(User $user)
    {  
        return $this->successResponse(new UserResource($user->load('roles')));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        // 1. تحديث الحالة (Active/Inactive)
        if ($request->has('status')) {
            $user->status = $request->status;
        }

        // 2. تحديث الأدوار (Assign Roles)
        if ($request->has('roles')) {
            $user->syncRoles($request->roles); // تمسح القديم وبتضيف جديد 
        }
        $user->save();
        return $this->successResponse(new UserResource($user->load('roles')), __('messages.user_updated'));
    }

    /**
     * تغيير حالة المستخدم بسرعة (Toggle Status)
     */
    public function toggleStatus(User $user)
    {
        // منع المدير من تعطيل حسابه الشخصي بالخطأ (Safety Check)
        if ($user->id === auth()->id()) {
            return $this->errorResponse(__('messages.cannot_toggle_own_status'), 403);
        }

        $user->status = ($user->status === 'active') ? 'inactive' : 'active';
        $user->save();
        
        return $this->successResponse(null, __('messages.user_status_toggled', ['status' => $user->status]));
    }

    /**
     * إعادة تعيين كلمة المرور من قبل المسؤول
     */
    public function resetPassword(ResetUserPasswordRequest $request, User $user)
    {
        $data = $request->validated();

        $user->password = Hash::make($data['password']);
        $user->save();

        return $this->successResponse(null, __('messages.password_reset_success'));
    }

}
