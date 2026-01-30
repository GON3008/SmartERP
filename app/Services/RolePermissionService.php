<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

class RolePermissionService
{
    /**
     * Get all roles
     */
    public function getAllRoles(array $filters = [])
    {
        $query = Role::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->withCount(['users', 'permissions'])
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get role by ID
     */
    public function getRoleById(int $id)
    {
        return Role::with(['permissions', 'users'])->findOrFail($id);
    }

    /**
     * Create role
     */
    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            // Assign permissions
            if (!empty($data['permission_ids'])) {
                $role->permissions()->attach($data['permission_ids']);
            }

            return $role->load('permissions');
        });
    }

    /**
     * Update role
     */
    public function updateRole(int $id, array $data): Role
    {
        return DB::transaction(function () use ($id, $data) {
            $role = Role::findOrFail($id);

            // Prevent editing Super Admin
            if ($role->name === 'Super Admin') {
                throw new \Exception('Không thể chỉnh sửa role Super Admin!');
            }

            $role->update([
                'name' => $data['name'] ?? $role->name,
                'description' => $data['description'] ?? $role->description,
            ]);

            // Sync permissions
            if (isset($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            return $role->fresh(['permissions']);
        });
    }

    /**
     * Delete role
     */
    public function deleteRole(int $id): bool
    {
        $role = Role::findOrFail($id);

        // Prevent deleting Super Admin
        if ($role->name === 'Super Admin') {
            throw new \Exception('Không thể xóa role Super Admin!');
        }

        // Check if role has users
        if ($role->users()->exists()) {
            throw new \Exception('Không thể xóa role đang được sử dụng bởi users!');
        }

        return DB::transaction(function () use ($role) {
            $role->permissions()->detach();
            return $role->delete();
        });
    }

    /**
     * Assign permission to role
     */
    public function assignPermissionToRole(int $roleId, int $permissionId): Role
    {
        $role = Role::findOrFail($roleId);
        $role->permissions()->syncWithoutDetaching($permissionId);

        return $role->fresh(['permissions']);
    }

    /**
     * Remove permission from role
     */
    public function removePermissionFromRole(int $roleId, int $permissionId): Role
    {
        $role = Role::findOrFail($roleId);
        $role->permissions()->detach($permissionId);

        return $role->fresh(['permissions']);
    }

    /**
     * Sync permissions to role
     */
    public function syncPermissionsToRole(int $roleId, array $permissionIds): Role
    {
        $role = Role::findOrFail($roleId);
        $role->permissions()->sync($permissionIds);

        return $role->fresh(['permissions']);
    }

    // ==================== PERMISSION METHODS ====================

    /**
     * Get all permissions
     */
    public function getAllPermissions(array $filters = [])
    {
        $query = Permission::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Group by module
        if (!empty($filters['group_by_module'])) {
            return $this->getPermissionsGroupedByModule();
        }

        return $query->withCount('roles')
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 50);
    }

    /**
     * Get permissions grouped by module
     */
    public function getPermissionsGroupedByModule()
    {
        $permissions = Permission::orderBy('name')->get();

        $grouped = [];
        foreach ($permissions as $permission) {
            // Extract module from permission name (e.g., "view.products" -> "products")
            $parts = explode('.', $permission->name);
            $module = $parts[1] ?? 'other';

            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }

            $grouped[$module][] = $permission;
        }

        return $grouped;
    }

    /**
     * Get permission by ID
     */
    public function getPermissionById(int $id)
    {
        return Permission::with('roles')->findOrFail($id);
    }

    /**
     * Create permission
     */
    public function createPermission(array $data): Permission
    {
        return Permission::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Update permission
     */
    public function updatePermission(int $id, array $data): Permission
    {
        $permission = Permission::findOrFail($id);

        $permission->update([
            'name' => $data['name'] ?? $permission->name,
            'description' => $data['description'] ?? $permission->description,
        ]);

        return $permission->fresh();
    }

    /**
     * Delete permission
     */
    public function deletePermission(int $id): bool
    {
        $permission = Permission::findOrFail($id);

        return DB::transaction(function () use ($permission) {
            $permission->roles()->detach();
            return $permission->delete();
        });
    }

    /**
     * Bulk create permissions
     */
    public function bulkCreatePermissions(array $permissions): array
    {
        $created = [];

        foreach ($permissions as $permission) {
            $created[] = Permission::create([
                'name' => $permission['name'],
                'description' => $permission['description'] ?? null,
            ]);
        }

        return $created;
    }

    /**
     * Generate standard CRUD permissions for a module
     */
    public function generateModulePermissions(string $module, ?string $description = null): array
    {
        $actions = ['view', 'create', 'edit', 'delete'];
        $permissions = [];

        foreach ($actions as $action) {
            $permissions[] = Permission::firstOrCreate(
                ['name' => "{$action}.{$module}"],
                ['description' => ucfirst($action) . ' ' . ucfirst($module) . ($description ? " - {$description}" : '')]
            );
        }

        return $permissions;
    }

    // ==================== UTILITY METHODS ====================

    /**
     * Get role statistics
     */
    public function getRoleStatistics(): array
    {
        return [
            'total_roles' => Role::count(),
            'roles_with_users' => Role::has('users')->count(),
            'average_permissions_per_role' => round(Role::withCount('permissions')->avg('permissions_count'), 2),
            'roles' => Role::withCount(['users', 'permissions'])->get()->map(function ($role) {
                return [
                    'name' => $role->name,
                    'users_count' => $role->users_count,
                    'permissions_count' => $role->permissions_count,
                ];
            }),
        ];
    }

    /**
     * Get permission statistics
     */
    public function getPermissionStatistics(): array
    {
        return [
            'total_permissions' => Permission::count(),
            'assigned_permissions' => Permission::has('roles')->count(),
            'unassigned_permissions' => Permission::doesntHave('roles')->count(),
        ];
    }

    /**
     * Clone role with permissions
     */
    public function cloneRole(int $roleId, string $newName): Role
    {
        return DB::transaction(function () use ($roleId, $newName) {
            $originalRole = Role::with('permissions')->findOrFail($roleId);

            $newRole = Role::create([
                'name' => $newName,
                'description' => $originalRole->description . ' (Clone)',
            ]);

            // Copy permissions
            $permissionIds = $originalRole->permissions->pluck('id')->toArray();
            $newRole->permissions()->attach($permissionIds);

            return $newRole->load('permissions');
        });
    }

    /**
     * Get users with specific permission
     */
    public function getUsersWithPermission(string $permissionName)
    {
        $permission = Permission::where('name', $permissionName)->firstOrFail();

        return $permission->roles()
            ->with('users')
            ->get()
            ->pluck('users')
            ->flatten()
            ->unique('id');
    }

    /**
     * Check if role has permission
     */
    public function roleHasPermission(int $roleId, string $permissionName): bool
    {
        $role = Role::findOrFail($roleId);
        return $role->permissions()->where('name', $permissionName)->exists();
    }
}
