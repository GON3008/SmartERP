<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserService
{
    /**
     * Get all users
     */
    public function getAllUsers(array $filters = [])
    {
        $query = User::query();

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by role
        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        // Filter by verified
        if (isset($filters['verified'])) {
            if ($filters['verified']) {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        return $query->with(['roles', 'employee'])
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $id)
    {
        return User::with(['roles.permissions', 'employee', 'activityLogs'])
            ->findOrFail($id);
    }

    /**
     * Create new user
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // Create user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'] ?? true,
                'email_verified_at' => $data['email_verified_at'] ?? null,
            ]);

            // Assign roles
            if (!empty($data['role_ids'])) {
                $user->roles()->attach($data['role_ids']);
            }

            return $user->load('roles');
        });
    }

    /**
     * Update user
     */
    public function updateUser(int $id, array $data): User
    {
        return DB::transaction(function () use ($id, $data) {
            $user = User::findOrFail($id);

            // Update basic info
            $updateData = [
                'name' => $data['name'] ?? $user->name,
                'email' => $data['email'] ?? $user->email,
            ];

            // Update password if provided
            if (!empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            // Update status if provided
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }

            $user->update($updateData);

            // Sync roles
            if (isset($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }

            return $user->fresh(['roles']);
        });
    }

    /**
     * Delete user
     */
    public function deleteUser(int $id): bool
    {
        $user = User::findOrFail($id);

        // Prevent deleting super admin
        if ($user->hasRole('Super Admin')) {
            throw new \Exception('Không thể xóa Super Admin!');
        }

        // Check if user has employee record
        if ($user->employee) {
            throw new \Exception('Không thể xóa user có liên kết với nhân viên! Xóa nhân viên trước.');
        }

        return DB::transaction(function () use ($user) {
            $user->roles()->detach();
            return $user->delete();
        });
    }

    /**
     * Activate/Deactivate user
     */
    public function toggleUserStatus(int $id): User
    {
        $user = User::findOrFail($id);

        // Prevent deactivating super admin
        if ($user->hasRole('Super Admin') && $user->status) {
            throw new \Exception('Không thể vô hiệu hóa Super Admin!');
        }

        $user->update(['status' => !$user->status]);

        return $user->fresh();
    }

    /**
     * Assign role to user
     */
    public function assignRole(int $userId, int $roleId): User
    {
        $user = User::findOrFail($userId);
        $user->roles()->syncWithoutDetaching($roleId);

        return $user->fresh(['roles']);
    }

    /**
     * Remove role from user
     */
    public function removeRole(int $userId, int $roleId): User
    {
        $user = User::findOrFail($userId);

        // Prevent removing last role from super admin
        if ($user->hasRole('Super Admin') && $user->roles->count() === 1) {
            throw new \Exception('Không thể xóa role duy nhất của Super Admin!');
        }

        $user->roles()->detach($roleId);

        return $user->fresh(['roles']);
    }

    /**
     * Sync roles (replace all)
     */
    public function syncRoles(int $userId, array $roleIds): User
    {
        $user = User::findOrFail($userId);

        // Prevent removing super admin role
        if ($user->hasRole('Super Admin')) {
            $superAdminRole = \App\Models\Role::where('name', 'Super Admin')->first();
            if ($superAdminRole && !in_array($superAdminRole->id, $roleIds)) {
                throw new \Exception('Không thể xóa role Super Admin!');
            }
        }

        $user->roles()->sync($roleIds);

        return $user->fresh(['roles']);
    }

    /**
     * Change user password (admin)
     */
    public function changeUserPassword(int $userId, string $newPassword): User
    {
        $user = User::findOrFail($userId);

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        return $user;
    }

    /**
     * Get users by role
     */
    public function getUsersByRole(string $roleName)
    {
        return User::whereHas('roles', function ($q) use ($roleName) {
            $q->where('name', $roleName);
        })
            ->with(['roles', 'employee'])
            ->get();
    }

    /**
     * Get user statistics
     */
    public function getUserStatistics(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('status', true)->count(),
            'inactive_users' => User::where('status', false)->count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'unverified_users' => User::whereNull('email_verified_at')->count(),
            'users_by_role' => \App\Models\Role::withCount('users')->get()->map(function ($role) {
                return [
                    'role' => $role->name,
                    'count' => $role->users_count,
                ];
            }),
        ];
    }

    /**
     * Search users
     */
    public function searchUsers(string $keyword)
    {
        return User::where('name', 'like', "%{$keyword}%")
            ->orWhere('email', 'like', "%{$keyword}%")
            ->with('roles')
            ->limit(20)
            ->get();
    }

    /**
     * Get recently logged in users
     */
    public function getRecentlyLoggedInUsers(int $limit = 10)
    {
        return User::whereNotNull('last_login_at')
            ->orderBy('last_login_at', 'desc')
            ->limit($limit)
            ->with('roles')
            ->get();
    }
}
