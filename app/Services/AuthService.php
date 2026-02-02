<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AuthService
{
    /**
     * Register new user
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => true,
        ]);

        // Assign default role
        $viewerRole = \App\Models\Role::where('name', 'Viewer')->first();
        if ($viewerRole) {
            $user->roles()->attach($viewerRole->id);
        }

        // Create token with expiration
        $expiresAt = $this->getTokenExpiresAt();
        $tokenResult = $user->createToken('auth_token', ['*'], $expiresAt);

        return [
            'user' => $user->load('roles'),
            'token' => $tokenResult->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt ? $expiresAt->toISOString() : null,
            'expires_in' => $expiresAt ? $expiresAt->diffInSeconds(now()) : null, // seconds
            'message' => 'Đăng ký thành công!'
        ];
    }

    /**
     * Login user
     */
    public function login(array $credentials, bool $remember = false): array
    {
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

        if (!Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác.'],
            ]);
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Create token with expiration
        $expiresAt = $remember ? $this->getTokenExpiresAt(30) : $this->getTokenExpiresAt(); // 30 days if remember
        $tokenResult = $user->createToken('auth_token', ['*'], $expiresAt);

        return [
            'user' => $user->load('roles.permissions'),
            'token' => $tokenResult->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt ? $expiresAt->toISOString() : null,
            'expires_in' => $expiresAt ? $expiresAt->diffInSeconds(now()) : null, // seconds
            'message' => 'Đăng nhập thành công!',
        ];
    }

    /**
     * Logout user (Revoke all tokens)
     */
    public function logout(): void
    {
        $user = Auth::user();

        if ($user) {
            // Revoke all tokens
            $user->tokens()->delete();
        }
    }

    /**
     * Logout from current device only
     */
    public function logoutCurrentDevice(): void
    {
        $user = Auth::user();

        if ($user) {
            // Get current token
            $currentToken = $user->currentAccessToken();

            if ($currentToken) {
                // Revoke current token only
                $user->tokens()->where('id', $currentToken->id)->delete();
            }
        }
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

        // Revoke all tokens after password change (security)
        $user->tokens()->delete();

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

                // Revoke all tokens after password reset
                $user->tokens()->delete();
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

    /**
     * Get user's active tokens
     */
    public function getActiveTokens(User $user)
    {
        return $user->tokens()->get();
    }

    /**
     * Revoke specific token
     */
    public function revokeToken(User $user, int $tokenId): bool
    {
        return $user->tokens()->where('id', $tokenId)->delete() > 0;
    }

    /**
     * Revoke all tokens except current
     */
    public function revokeAllTokensExceptCurrent(): void
    {
        $user = Auth::user();

        if ($user) {
            $currentToken = $user->currentAccessToken();

            if ($currentToken) {
                $currentTokenId = $currentToken->id;
                $user->tokens()->where('id', '!=', $currentTokenId)->delete();
            }
        }
    }

    /**
     * Get token expiration time
     *
     * @param int|null $days Number of days (override config)
     * @return \Carbon\Carbon|null
     */
    private function getTokenExpiresAt(?int $days = null): ?Carbon
    {
        if ($days !== null) {
            return Carbon::now()->addDays($days);
        }

        $expirationMinutes = config('sanctum.expiration');

        if ($expirationMinutes === null) {
            return null; // No expiration
        }

        return Carbon::now()->addMinutes((int) $expirationMinutes);
    }

    /**
     * Refresh token (revoke old, create new)
     */
    public function refreshToken(): array
    {
        $user = Auth::user();

        // Revoke current token
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $user->tokens()->where('id', $currentToken->id)->delete();
        }

        // Create new token
        $expiresAt = $this->getTokenExpiresAt();
        $tokenResult = $user->createToken('auth_token', ['*'], $expiresAt);

        return [
            'token' => $tokenResult->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt ? $expiresAt->toISOString() : null,
            'expires_in' => $expiresAt ? $expiresAt->diffInSeconds(now()) : null,
            'message' => 'Token đã được làm mới!',
        ];
    }

    /**
     * Get current token info
     */
    public function getTokenInfo(): array
    {
        $user = Auth::user();
        $currentToken = $user->currentAccessToken();

        if (!$currentToken) {
            return [
                'valid' => false,
                'message' => 'No active token'
            ];
        }

        $expiresAt = $currentToken->expires_at;
        $isExpired = $expiresAt ? Carbon::parse($expiresAt)->isPast() : false;

        return [
            'valid' => !$isExpired,
            'token_id' => $currentToken->id,
            'name' => $currentToken->name,
            'abilities' => $currentToken->abilities,
            'created_at' => $currentToken->created_at->toISOString(),
            'expires_at' => $expiresAt ? Carbon::parse($expiresAt)->toISOString() : null,
            'is_expired' => $isExpired,
            'time_remaining' => $expiresAt && !$isExpired
                ? Carbon::parse($expiresAt)->diffForHumans()
                : null,
        ];
    }

    /**
     * Check if token is expired
     */
    public function isTokenExpired(): bool
    {
        $user = Auth::user();
        $currentToken = $user->currentAccessToken();

        if (!$currentToken || !$currentToken->expires_at) {
            return false; // No expiration
        }

        return Carbon::parse($currentToken->expires_at)->isPast();
    }
}
