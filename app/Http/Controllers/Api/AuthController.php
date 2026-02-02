<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\User\StoreUserRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use PhpParser\Node\Expr\FuncCall;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register new user
     */

    public function register(StoreUserRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->register($request->validated());

            return response()->json([
                'message' => 'Đăng ký thành công!',
                'user' => $user,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Đăng ký thất bại!' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Login user
     */

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->only('email', 'password'),
                $request->boolean('remember')
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Logout user (revoke all tokens)
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'message' => 'Đăng xuất thành công!'
        ]);
    }

    /**
     * Logout current device only
     */
    public function logoutCurrentDevice(): JsonResponse
    {
        $this->authService->logoutCurrentDevice();

        return response()->json([
            'message' => 'Đã đăng xuất khỏi thiết bị này!'
        ]);
    }

    /**
     * Get authenticated user
     */

    public function me(): JsonResponse
    {
        $user = $this->authService->getAuthenticatedUser();

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Change password
     */

    // public function changePassword(Request $request): JsonResponse
    // {
    //     $request->validate([
    //         'current_password' => 'required|string',
    //         'new_password' => 'required|string|min:8|confirmed',
    //     ]);

    //     try {
    //         $this->authService->changePassword(
    //             $request->user(),
    //             $request->current_password,
    //             $request->new_password
    //         );

    //         return response()->json([
    //             'message' => 'Đổi mật khẩu thành công!',
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => $e->getMessage(),
    //         ], 422);
    //     }
    // }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $this->authService->changePassword(
                $request->user(),
                $request->current_password,
                $request->new_password
            );

            return response()->json([
                'message' => 'Đổi mật khẩu thành công! Tất cả tokens đã bị thu hồi.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Refresh token
     */
    public function refreshToken(): JsonResponse
    {
        try {
            $result = $this->authService->refreshToken();

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Làm mới token thất bại: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get current token info
     */
    public function tokenInfo(): JsonResponse
    {
        $info = $this->authService->getTokenInfo();

        return response()->json([
            'data' => $info
        ]);
    }

    /**
     * Get all active tokens
     */
    public function activeTokens(): JsonResponse
    {
        $user = Auth::user();
        $tokens = $this->authService->getActiveTokens($user);

        return response()->json([
            'data' => $tokens->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'created_at' => $token->created_at->toISOString(),
                    'expires_at' => $token->expires_at
                        ? \Carbon\Carbon::parse($token->expires_at)->toISOString()
                        : null,
                    'is_current' => $token->id == Auth::user()->currentAccessToken()->id,
                ];
            })
        ]);
    }

    /**
     * Revoke specific token
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $request->validate([
            'token_id' => 'required|integer'
        ]);

        try {
            $user = Auth::user();
            $result = $this->authService->revokeToken($user, $request->token_id);

            if ($result) {
                return response()->json([
                    'message' => 'Token đã được thu hồi!'
                ]);
            }

            return response()->json([
                'message' => 'Token không tồn tại!'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Revoke all tokens except current
     */
    public function revokeOtherTokens(): JsonResponse
    {
        $this->authService->revokeAllTokensExceptCurrent();

        return response()->json([
            'message' => 'Đã thu hồi tất cả tokens khác!'
        ]);
    }


    /**
     * Send password reset link
     */

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        try {
            $message = $this->authService->sendResetLinkEmail($request->email);

            return response()->json([
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reset password
     */

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        try {
            $message = $this->authService->resetPassword($request->only(
                'email', 'password', 'password_confirmation', 'token'
            ));

            return response()->json([
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
