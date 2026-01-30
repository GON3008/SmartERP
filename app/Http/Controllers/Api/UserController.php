<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Services\ActivityLogService;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use PhpParser\Node\Expr\FuncCall;

class UserController extends Controller
{
    protected $userService;
    protected $logService;

    public function __construct(UserService $userService, ActivityLogService $logService)
    {
        $this->userService = $userService;
        $this->logService = $logService;
    }

    /**
     * Display a listing of users
     */

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'role', 'verified', 'per_page']);
        $users = $this->userService->getAllUsers($filters);

        return response()->json($users);
    }

    /**
     * Store a newly created user
     */

    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->createUser($request->validated());

            $this->logService->log('created', 'users', $user->id, "Tạo user: {$user->name}");

            return response()->json([
                'message' => 'Create user successfully!',
                'data' => $user,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified user
     */

    public function show(int $id): JsonResponse
    {
        try {
            $user = $this->userService->getUserById($id);

            return response()->json([
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'User not found!' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified user
     */

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->userService->updateUser($id, $request->validated());

            $this->logService->log('updated', 'users', $user->id, "Cập nhật user: {$user->name}");

            return response()->json([
                'message' => 'Update user successfully!',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete the specified user
     */

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->userService->deleteUser($id);

            $this->logService->log('deleted', 'users', $id, "Xóa user ID: {$id}");

            return response()->json([
                'message' => 'Delete user successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Toggle user status
     */

    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $user = $this->userService->toggleUserStatus($id);

            $status = $user->status ? 'enabled' : 'disabled';
            $this->logService->log('updated', 'users', $user->id, "Đã {$status} user: {$user->name}");

            return response()->json([
                'message' => 'Toggle user status successfully!',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Assign role to user
     */

    public function assignRole(Request $request, int $id): JsonResponse
    {
        $request->validate(['role_id' => 'required|exists:roles,id']);

        try {
            $user = $this->userService->assignRole($id, $request->role_id);

            return response()->json([
                'message' => 'Assign role to user successfully!',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove role from user
     */

    public function removeRole(Request $request, int $id): JsonResponse
    {
        $request->validate(['role_id' => 'required|exists:roles,id']);

        try {
            $user = $this->userService->removeRole($id, $request->role_id);

            return response()->json([
                'message' => 'Remove role from user successfully!',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get user statistics
     */

    public function statistics(): JsonResponse
    {
        $status = $this->userService->getUserStatistics();

        return response()->json([
            'data' => $status,
        ]);
    }
}
