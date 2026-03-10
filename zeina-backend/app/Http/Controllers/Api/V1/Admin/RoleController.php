<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreRoleRequest;
use App\Http\Resources\Api\V1\Role\RoleResource;
use App\Models\Role;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::with('permissions')->withCount('users')->get();
        return $this->successResponse(RoleResource::collection($roles),__('messages.roles_retrieved'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        DB::beginTransaction();
        try {
            $role = Role::create([
                'name'        => $request->name,
                'code'        => $request->code,
                'description' => $request->description,
                'status'      => $request->status,
                'guard_name'  => 'web', 
            ]);

            $role->syncPermissions($request->permissions);
            DB::commit();
            return $this->successResponse(null,__('messages.role_create'));
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e->getMessage());
            Log::error($e->getMessage());
            return $this->errorResponse(__('messages.role_create_failed'), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRoleRequest $request, $role)
    {    
        $rolee = Role::findById($role, 'web');       
        $rolee->update($request->only(['name', 'code', 'description', 'status']));
        $rolee->syncPermissions($request->permissions);

        return $this->successResponse(null,__('messages.role_update'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    { 
        if ($role->name === 'Super Admin') {
            return $this->errorResponse(__('messages.cannot_delete_super_admin_role'), 403);
        }
        // BR-05: منع حذف دور مرتبط بمستخدمين
        if ($role->users()->count() > 0) {
            return $this->errorResponse(__('messages.cannot_delete_role_with_users'), 403);
        }
        $role->delete();
        return $this->successResponse(null, __('messages.role_deleted'));
    }

    public function duplicate(Role $role)
    {
        $newRole = Role::create([
            'name'        => $role->name . ' - Copy',
            'code'        => $role->code . '_COPY',
            'description' => $role->description,
            'status'      => 'inactive',
            'guard_name'  => $role->guard_name,
        ]);

        $newRole->syncPermissions($role->permissions);

        return $this->successResponse(null, __('messages.role_duplicated'));
    }
}
