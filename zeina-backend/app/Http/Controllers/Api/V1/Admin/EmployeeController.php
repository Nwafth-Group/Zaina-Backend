<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreEmployeeRequest;
use App\Http\Requests\Api\V1\Admin\UpdateEmployeeRequest;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
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
        $query = User::whereIn('type', ['operation', 'super_admin', 'driver'])->with('roles');   
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhere('email','like', "%{$search}%");
            });
        }
        
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return $this->successResponse( UserResource::collection($query->paginate(15)), __('messages.employee_fetched_success'));
    }

    /**
     * US-05: Create New Employee User
     */
    public function store(StoreEmployeeRequest $request)
    { 
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $data['password'] = Hash::make($data['password']); // تشفير الباسورد
            $employee = User::create($data);
            // إسناد الصلاحيات (Roles)
            $employee->assignRole($request->roles);

            DB::commit();
            return $this->successResponse( new UserResource($employee->load('roles')), __('messages.employee_created_successfully'), 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create employee: ' . $e->getMessage(), 500);
        }
    }  

    public function show(User $employee){
       
        return $this->successResponse(new UserResource($employee->load('roles')),__('messages.employee_show_success'));
    }

    /**
     * US-05: Edit Employee
     */
    public function update(UpdateEmployeeRequest $request, User $employee)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();

            // تحديث الباسورد فقط إذا تم إرسال باسورد جديد
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $employee->update($data);
            
            // تحديث الصلاحيات
            $employee->syncRoles($request->roles);

            DB::commit();
            return $this->successResponse(null,__('messages.employee_updated_successfully'));

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update employee: ' . $e->getMessage(), 500);
        }
    }

    /**
     * US-05: Deactivate Employee / Safe Delete
     */
    public function toggleStatus(User $employee)
    {
        // Scenario 4: منع إيقاف آخر Super Admin
       
        if ($employee->hasRole('Super Admin') && $employee->status === 'active') {
            $superAdminsCount = User::role('Super Admin')->where('status', 'active')->count();
            if ($superAdminsCount <= 1) {
                return $this->errorResponse(__('messages.cannot_deactivate_last_super_admin'), 403);
            }
        }

        $employee->status = $employee->status === 'active' ? 'inactive' : 'active';
        $employee->save();

        $msg = $employee->status === 'inactive' ? __('messages.employee_deactivated') : __('messages.employee_activated');
        return $this->successResponse(null, $msg);
    }
}
