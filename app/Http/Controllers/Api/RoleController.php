<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RolePermissionService;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    protected $roleService;

    public function __construct(RolePermissionService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'per_page']);
        return response()->json($this->roleService->getAllRoles($filters));
    }

    public function store(StoreRoleRequest $request)
    {
        try {
            $role = $this->roleService->createRole($request->validated());
            return response()->json(['message' => 'Tạo role thành công!', 'data' => $role], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function show(int $id)
    {
        return response()->json(['data' => $this->roleService->getRoleById($id)]);
    }

    public function update(UpdateRoleRequest $request, int $id)
    {
        try {
            $role = $this->roleService->updateRole($id, $request->validated());
            return response()->json(['message' => 'Cập nhật thành công!', 'data' => $role]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->roleService->deleteRole($id);
            return response()->json(['message' => 'Xóa role thành công!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function assignPermission(Request $request, int $id)
    {
        $request->validate(['permission_id' => 'required|exists:permissions,id']);

        $role = $this->roleService->assignPermissionToRole($id, $request->permission_id);
        return response()->json(['message' => 'Gán quyền thành công!', 'data' => $role]);
    }

    public function syncPermissions(Request $request, int $id)
    {
        $request->validate(['permission_ids' => 'required|array', 'permission_ids.*' => 'exists:permissions,id']);

        $role = $this->roleService->syncPermissionsToRole($id, $request->permission_ids);
        return response()->json(['message' => 'Đồng bộ quyền thành công!', 'data' => $role]);
    }
}
