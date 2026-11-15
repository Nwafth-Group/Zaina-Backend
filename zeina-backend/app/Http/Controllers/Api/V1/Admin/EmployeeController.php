<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Filters\Employee\RoleFilter;
use App\Filters\Employee\SearchFilter;
use App\Filters\Employee\StatusFilter;
use App\Filters\Employee\TypeFilter;
use App\Filters\Employee\CreatedFromFilter;
use App\Filters\Employee\CreatedToFilter;
use App\Filters\Employee\PermissionFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreEmployeeRequest;
use App\Http\Requests\Api\V1\Admin\UpdateEmployeeRequest;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Employees are the internal staff user types; filter through the Pipeline.
        $query = app(Pipeline::class)
            ->send(User::whereIn('type', ['operation', 'super_admin', 'driver'])->with('roles'))
            ->through([
                SearchFilter::class,
                TypeFilter::class,
                StatusFilter::class,
                RoleFilter::class,
                PermissionFilter::class,
                CreatedFromFilter::class,
                CreatedToFilter::class,
            ])
            ->thenReturn();

        // Safe dynamic sorting (allow-list).
        $allowedSorts = ['first_name', 'created_at', 'id'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'created_at';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $baseEmployeeQuery = User::whereIn('type', ['operation', 'super_admin', 'driver']);
        $counts = [
            'all' => (clone $baseEmployeeQuery)->count(),
            'active' => (clone $baseEmployeeQuery)->where('status', 'active')->count(),
            'inactive' => (clone $baseEmployeeQuery)->where('status', 'inactive')->count(),
            'operation' => (clone $baseEmployeeQuery)->where('type', 'operation')->count(),
            'driver' => (clone $baseEmployeeQuery)->where('type', 'driver')->count(),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $employees = $query->paginate($perPage)->withQueryString();

        $itemsResource = UserResource::collection($employees);
        $meta = [
            'total' => $employees->total(),
            'per_page' => $employees->perPage(),
            'current_page' => $employees->currentPage(),
            'last_page' => $employees->lastPage(),
        ];

        return $this->successResponse([
            'items' => $itemsResource,
            'counts' => $counts,
            'meta' => $meta,
        ], __('messages.employee_fetched_success'));
    }

    /**
     * US-05: Create New Employee User
     */
    public function store(StoreEmployeeRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $plainPassword = $data['password'];
            $data['password'] = Hash::make($plainPassword); // تشفير الباسورد
            $employee = User::create($data);
            // إسناد الصلاحيات (Roles)
            $employee->assignRole($request->roles);

            if ($request->hasFile('image')) {
                app(\App\Service\MediaService::class)->upload(
                    $request->file('image'),
                    $employee,
                    'user/profilePhoto',
                    'public'
                );
            }

            DB::commit();

            // إرسال بريد الترحيب للموظف شاملاً بيانات الدخول ورابط التحكم ومعلومات الشركة (Queued)
            try {
                $companyInfo = \App\Models\CompanyInfo::with('media')->first();
                \Illuminate\Support\Facades\Mail::to($employee->email)->send(
                    new \App\Mail\EmployeeWelcomeMail(
                        $employee->load('roles'),
                        $plainPassword,
                        $companyInfo,
                        config('app.admin_portal_url'),
                        isUpdate: false
                    )
                );
            } catch (\Exception $mailEx) {
                \Illuminate\Support\Facades\Log::error("Failed to send welcome email to employee {$employee->id}: " . $mailEx->getMessage());
            }

            \Illuminate\Support\Facades\Log::info("Employee created successfully: User ID {$employee->id} by user ID " . auth()->id());

            return $this->successResponse(new UserResource($employee->load('roles')), __('messages.employee_created_successfully'), 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Failed to create employee: '.$e->getMessage(), 500);
        }
    }

    public function show(User $employee)
    {

        return $this->successResponse(new UserResource($employee->load('roles')), __('messages.employee_show_success'));
    }

    /**
     * US-05: Edit Employee
     */
    public function update(UpdateEmployeeRequest $request, User $employee)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $plainPassword = !empty($data['password']) ? $data['password'] : null;

            // تحديث الباسورد فقط إذا تم إرسال باسورد جديد
            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $employee->update($data);

            // تحديث الصلاحيات
            $employee->syncRoles($request->roles);

            if ($request->hasFile('image')) {
                if ($employee->media()->where('collection', 'user/profilePhoto')->exists()) {
                    $oldMedia = $employee->media()->where('collection', 'user/profilePhoto')->first();
                    app(\App\Service\MediaService::class)->delete($oldMedia);
                }
                app(\App\Service\MediaService::class)->upload(
                    $request->file('image'),
                    $employee,
                    'user/profilePhoto',
                    'public'
                );
            }

            DB::commit();

            // إرسال بريد تحديث البيانات للموظف (Queued)
            try {
                $companyInfo = \App\Models\CompanyInfo::with('media')->first();
                \Illuminate\Support\Facades\Mail::to($employee->email)->send(
                    new \App\Mail\EmployeeWelcomeMail(
                        $employee->fresh('roles'),
                        $plainPassword,
                        $companyInfo,
                        config('app.admin_portal_url'),
                        isUpdate: true
                    )
                );
            } catch (\Exception $mailEx) {
                \Illuminate\Support\Facades\Log::error("Failed to send update email to employee {$employee->id}: " . $mailEx->getMessage());
            }

            \Illuminate\Support\Facades\Log::info("Employee updated successfully: User ID {$employee->id} by user ID " . auth()->id());

            return $this->successResponse(null, __('messages.employee_updated_successfully'));

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Failed to update employee: '.$e->getMessage(), 500);
        }
    }

    /**
     * US-05: Deactivate Employee / Safe Delete
     */
    public function toggleStatus(User $employee)
    {
        // Super Admin cannot be deactivated by regular admins (operations staff)
        if ($employee->hasRole('Super Admin') && auth()->user()->type !== 'super_admin') {
            return $this->errorResponse(__('messages.unauthorized') ?? 'غير مصرح لك بتعديل حالة مدير عام.', 403);
        }

        // Scenario 4: منع إيقاف آخر Super Admin
        if ($employee->hasRole('Super Admin') && $employee->status === 'active') {
            $superAdminsCount = User::role('Super Admin')->where('status', 'active')->count();
            if ($superAdminsCount <= 1) {
                return $this->errorResponse(__('messages.cannot_deactivate_last_super_admin'), 403);
            }
        }

        $employee->status = $employee->status === 'active' ? 'inactive' : 'active';
        $employee->save();

        \Illuminate\Support\Facades\Log::info("Employee status toggled: User ID {$employee->id} status set to {$employee->status} by user ID " . auth()->id());

        $msg = $employee->status === 'inactive' ? __('messages.employee_deactivated') : __('messages.employee_activated');

        return $this->successResponse(null, $msg);
    }

    /**
     * Delete Employee / Hard Delete with relations validation
     */
    public function destroy(User $employee)
    {
        // Gating Super Admin protection
        if ($employee->hasRole('Super Admin') && auth()->user()->type !== 'super_admin') {
            return $this->errorResponse(__('messages.unauthorized') ?? 'غير مصرح لك بحذف مدير عام.', 403);
        }

        // Prevent deleting the last Super Admin
        if ($employee->hasRole('Super Admin')) {
            $superAdminsCount = User::role('Super Admin')->where('status', 'active')->count();
            if ($superAdminsCount <= 1) {
                return $this->errorResponse(
                    app()->getLocale() === 'ar' 
                        ? 'لا يمكن حذف آخر مدير عام فعال بالنظام.' 
                        : 'Cannot delete the last active Super Admin in the system.', 
                    403
                );
            }
        }

        // Check for relationships
        $hasBookings = \App\Models\Booking::where('customer_id', $employee->id)->exists();
        $hasReports = \App\Models\Report::where('assigned_admin_id', $employee->id)
            ->orWhere('customer_id', $employee->id)
            ->exists();
        $hasHistory = \App\Models\BookingHistory::where('created_by', $employee->id)->exists();

        if ($hasBookings || $hasReports || $hasHistory) {
            return $this->errorResponse(
                app()->getLocale() === 'ar' 
                    ? 'لا يمكن حذف الموظف لوجود سجلات مرتبطة به في النظام (حجوزات، بلاغات، أو سجلات حالة).' 
                    : 'Cannot delete the employee because there are associated records (bookings, reports, or history logs) in the system.',
                400
            );
        }

        // Delete the employee profile media if exists
        $employee->media()->delete();
        $employee->roles()->detach();
        $employee->delete();

        return $this->successResponse(null, app()->getLocale() === 'ar' ? 'تم حذف الموظف بنجاح.' : 'Employee deleted successfully.');
    }
}
