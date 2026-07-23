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
        $counts = [
            'all' => Role::count(),
            'active' => Role::where('status', 'active')->count(),
            'inactive' => Role::where('status', 'inactive')->count(),
        ];

        return $this->successResponse([
            'items' => RoleResource::collection($roles),
            'counts' => $counts,
        ], __('messages.roles_retrieved'));
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
                'guard_name'  => 'sanctum', 
            ]);

            $role->syncPermissions($request->permissions);
            DB::commit();

            Log::info("Role created successfully: {$role->name} (ID: {$role->id}) by user ID " . auth()->id());

            return $this->successResponse(new RoleResource($role), __('messages.role_create'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Role creation failed: " . $e->getMessage());
            return $this->errorResponse(__('messages.role_create_failed'), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        return $this->successResponse(new RoleResource($role), __('messages.role_retrieved'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRoleRequest $request, $role)
    {    
        if (!auth()->user()->hasRole('Super Admin')) {
            return $this->errorResponse(__('messages.unauthorized') ?? 'غير مصرح لك.', 403);
        }

        $rolee = Role::findById($role, 'sanctum');       
        $rolee->update($request->only(['name', 'code', 'description', 'status']));
        $rolee->syncPermissions($request->permissions);

        Log::info("Role updated successfully: {$rolee->name} (ID: {$rolee->id}) by user ID " . auth()->id());

        return $this->successResponse(new RoleResource($rolee), __('messages.role_update'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($role)
    { 
        if (!auth()->user()->hasRole('Super Admin')) {
            return $this->errorResponse(__('messages.unauthorized') ?? 'غير مصرح لك.', 403);
        }

        $roleModel = $role instanceof Role ? $role : Role::findById($role, 'sanctum');

        if ($roleModel->name === 'Super Admin') {
            return $this->errorResponse(__('messages.cannot_delete_super_admin_role'), 403);
        }

        // BR-05: منع حذف دور مرتبط بمستخدمين
        if ($roleModel->users()->count() > 0) {
            return $this->errorResponse(__('messages.cannot_delete_role_with_users'), 403);
        }

        $roleName = $roleModel->name;
        $roleId = $roleModel->id;
        $roleModel->delete();

        Log::info("Role deleted successfully: {$roleName} (ID: {$roleId}) by user ID " . auth()->id());

        return $this->successResponse(null, __('messages.role_deleted'));
    }

    public function duplicate($role)
    {
        if (!auth()->user()->hasRole('Super Admin')) {
            return $this->errorResponse(__('messages.unauthorized') ?? 'غير مصرح لك.', 403);
        }

        $roleModel = $role instanceof Role ? $role : Role::findById($role, 'sanctum');

        $newRole = Role::create([
            'name'        => $roleModel->name . ' - Copy',
            'code'        => $roleModel->code . '_COPY',
            'description' => $roleModel->description,
            'status'      => 'inactive',
            'guard_name'  => 'sanctum',
        ]);

        $newRole->syncPermissions($roleModel->permissions);

        Log::info("Role duplicated: {$roleModel->name} cloned to {$newRole->name} (ID: {$newRole->id}) by user ID " . auth()->id());

        return $this->successResponse(new RoleResource($newRole), __('messages.role_duplicated'));
    }

    /**
     * List all permissions grouped by module.
     */
    public function permissions()
    {
        $permissions = \Spatie\Permission\Models\Permission::all();
        
        $grouped = $permissions->groupBy(function ($permission) {
            $parts = explode('.', $permission->name);
            return $parts[0] ?? 'general';
        })->map(function ($items) {
            return $items->map(function ($item) {
                $parts = explode('.', $item->name);
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'action' => $parts[1] ?? $item->name,
                ];
            });
        });

        return $this->successResponse($grouped, __('messages.permissions_retrieved'));
    }
}
