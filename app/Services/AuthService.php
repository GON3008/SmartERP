<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Register new user
     */
    public function register(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => true,
        ]);

        // Assign default role
        if (!empty($data['role_ids'])) {
            $user->roles()->attach($data['role_ids']);
        } else {
            // Assign default "Viewer" role
            $viewerRole = \App\Models\Role::where('name', 'Viewer')->first();
            if ($viewerRole) {
                $user->roles()->attach($viewerRole->id);
            }
        }

        // Fire registered event (for email verification)
        event(new Registered($user));

        return $user->load('roles');
    }

    /**
     * Login user
     */
    public function login(array $credentials, bool $remember = false): array
    {
        // Check if user exists and is active
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Email không tồn tại trong hệ thống.'],
            ]);
        }

        if (!$user->status) {
            throw ValidationException::withMessages([
                'email' => ['Tài khoản đã bị vô hiệu hóa.'],
            ]);
        }

        // Attempt login
        if (!Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác.'],
            ]);
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Create token for API (if using Sanctum)
        // $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user->load('roles.permissions'),
            'message' => 'Đăng nhập thành công!',
            // 'token' => $token, // Uncomment if using API tokens
        ];
    }

    /**
     * Logout user
     */
    public function logout(): void
    {
        // Revoke all tokens (if using Sanctum)
        // Auth::user()->tokens()->delete();

        Auth::logout();
    }

    /**
     * Get authenticated user
     */
    public function getAuthenticatedUser()
    {

        return Auth::user()->load('roles.permissions', 'employee');
    }

    /**
     * Change password
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        // Verify current password
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mật khẩu hiện tại không đúng.'],
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        return true;
    }

    /**
     * Reset password request
     */
    public function sendResetLinkEmail(string $email): string
    {
        $status = Password::sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return __($status);
    }

    /**
     * Reset password
     */
    public function resetPassword(array $credentials): string
    {
        $status = Password::reset(
            $credentials,
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return __($status);
    }

    /**
     * Verify email
     */
    public function verifyEmail(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->markEmailAsVerified();
        return true;
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw new \Exception('Email đã được xác thực.');
        }

        $user->sendEmailVerificationNotification();
    }

    /**
     * Check if user has permission
     */
    public function checkPermission(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }

    /**
     * Check if user has role
     */
    public function checkRole(User $user, string $role): bool
    {
        return $user->hasRole($role);
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions(User $user): array
    {
        $permissions = [];

        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $permissions[] = $permission->name;
            }
        }

        return array_unique($permissions);
    }
}
