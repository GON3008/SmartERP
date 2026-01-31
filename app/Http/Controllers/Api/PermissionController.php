<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RolePermissionService;
use App\Http\Requests\Permission\StorePermissionRequest;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    protected $roleService;

    public function __construct(RolePermissionService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'group_by_module', 'per_page']);
        return response()->json($this->roleService->getAllPermissions($filters));
    }

    public function store(StorePermissionRequest $request)
    {
        $permission = $this->roleService->createPermission($request->validated());
        return response()->json(['message' => 'Tạo permission thành công!', 'data' => $permission], 201);
    }

    public function generateModule(Request $request)
    {
        $request->validate([
            'module' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $permissions = $this->roleService->generateModulePermissions($request->module, $request->description);
        return response()->json(['message' => 'Tạo permissions cho module thành công!', 'data' => $permissions]);
    }
}
