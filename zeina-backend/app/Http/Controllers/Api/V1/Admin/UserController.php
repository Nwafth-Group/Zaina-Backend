<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\Media;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    use ApiResponseTrait;
    
    public function index(Request $request)
  {   
      // التحقق من الصلاحيات إذا كانت مفعلة عندك
      // abort_if(!auth()->user()->can('users.view'), 403, 'Unauthorized');
      $verification_status = $request->input('verification_status');
  
      if (in_array($verification_status, ['pending', 'rejected'])) {
          $query = User::with(['roles', 'media']);
      } else {
          $query = User::with('roles');
      }
  
      // 1. الفلترة بحالة التوثيق (الـ New Workflow)
      if ($verification_status) {
          $query->where('verification_status', $verification_status);
      }
  
      // 2. الفلترة بحالة الحساب العامة (active, inactive)
      if ($request->has('status')) {
          $query->where('status', $request->input('status'));
      }
  
      // 3. الفلترة بنوع المستخدم (customer, worker, admin)
      if ($request->has('type')) {
          $query->where('type', $request->input('type'));
      }
  
      // 4. الفلترة بالـ Role (الصلاحيات الإدارية)
      if ($request->has('role')) {
          $role = $request->input('role');
          $query->whereHas('roles', function ($r) use ($role) {
              $r->where('name', $role);
          });
      }
  
      // 5. الفلترة بالفترة الزمنية (تاريخ التسجيل)
      if ($request->input('date_from')) {
          $query->whereDate('created_at', '>=', $request->input('date_from'));
      }
  
      if ($request->input('date_to')) {
          $query->whereDate('created_at', '<=', $request->input('date_to'));
      }
  
      // 6. تفعيل الـ Search المتقدم للنصوص
      if ($request->filled('search')) {
          $this->applySearchFilter($query, $request->input('search'));
      }
  
      // 7. الترتيب الديناميكي الآمن (Sorting)
      $sortBy = $request->input('sort_by', 'created_at');
      $sortDir = $request->input('sort_dir', 'desc');
      $allowedSorts = ['created_at', 'status', 'verification_status', 'type', 'id'];
  
      if (in_array($sortBy, $allowedSorts)) {
          $query->orderBy($sortBy, $sortDir);
      } else {
          $query->latest(); // Fallback الافتراضي
      }
  
      // 8. التقسيم (Pagination)
      $perPage = $request->input('per_page', 15);
      $users = $query->paginate($perPage);
  
      return $this->successResponse(UserResource::collection($users), __('messages.users_retrieved'));
  }
    public function show(User $user)
    {  
        return $this->successResponse(new UserResource($user->load('roles')));
    }


  
    public function verifyUser(Request $request, User $user)
    { 
        $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|string|max:500|nullable'
        ], [
            'action.required' => __('messages.action_required'),
            'rejection_reason.required_if' => __('messages.rejection_reason_required')
        ]);

        if ($request->action === 'approve') {
            $user->verification_status = 'approved';
        } else {
            $user->verification_status = 'rejected';
            $user->rejection_reason = $request->rejection_reason; // حفظ سبب الرفض
        }

        $user->save();
        return $this->successResponse(null, 
            $request->action === 'approve' ? __('messages.user_approved_success') : __('messages.user_rejected_success')
        );
    }

    private function applySearchFilter($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('id_number', $search)
              ->orWhere('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone_number', 'like', "%{$search}%");
        });
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

    public function downloadKycDoc(Request $request, $mediaId)
{
    // 1. تأمين الإجراء: الأدمن فقط من يستطيع العبور
    // abort_if(!auth()->user()->tokenCan('admin'), 403);
    $mediaItem = Media::findOrFail($mediaId);

    // 2. التأكد من أن الملف ينتمي لديسك البرايفيت
    if ($mediaItem->disk !== 'private') {
        abort(404, 'الملف غير موجود في النطاق المحمي.');
    }

    // 3. قراءة الملف وتمريره كـ Stream آمن للمتصفح دون كشف مساره الفيزيائي
    if (!Storage::disk('private')->exists($mediaItem->path)) {
        abort(404, 'الملف الفيزيائي غير موجود على السيرفر.');
    }

    return Storage::disk('private')->response($mediaItem->path, $mediaItem->file_name);
}

}
