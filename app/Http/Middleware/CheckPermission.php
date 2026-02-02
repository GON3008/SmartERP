<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permissions  Format: "permission1|permission2" (OR) or "permission1,permission2" (AND)
     */
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $user = Auth::user();

        // Eager load roles and permissions
        if (!$user->relationLoaded('roles')) {
            $user->load('roles.permissions');
        }

        // Super Admin bypasses all permission checks
        if ($user->hasRole('Super Admin')) {
            return $next($request);
        }

        // Check for OR logic (user needs ANY of the permissions)
        if (str_contains($permissions, '|')) {
            $permissionArray = explode('|', $permissions);

            if (!$user->hasAnyPermission($permissionArray)) {
                return response()->json([
                    'message' => 'Bạn không có quyền truy cập tài nguyên này.',
                    'required_permissions' => $permissionArray,
                    'logic' => 'OR (cần ít nhất 1 quyền)',
                ], 403);
            }
        }
        // Check for AND logic (user needs ALL of the permissions)
        elseif (str_contains($permissions, ',')) {
            $permissionArray = explode(',', $permissions);
            
            if (!$user->hasAllPermissions($permissionArray)) {
                return response()->json([
                    'message' => 'Bạn không có đủ quyền truy cập tài nguyên này.',
                    'required_permissions' => $permissionArray,
                    'logic' => 'AND (cần tất cả các quyền)',
                ], 403);
            }
        }
        // Single permission check
        else {
            if (!$user->hasPermission($permissions)) {
                return response()->json([
                    'message' => 'Bạn không có quyền truy cập tài nguyên này.',
                    'required_permission' => $permissions,
                ], 403);
            }
        }

        return $next($request);
    }
}
