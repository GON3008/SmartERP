<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class RefreshTokenIfExpiring
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Only process if user is authenticated
        if ($user) {
            $currentToken = $user->currentAccessToken();

            if ($currentToken && $currentToken->expires_at) {
                $expiresAt = Carbon::parse($currentToken->expires_at);
                $now = Carbon::now();

                // Check if token expires in less than 5 minutes
                $minutesRemaining = $now->diffInMinutes($expiresAt, false);

                if ($minutesRemaining > 0 && $minutesRemaining <= 5) {
                    // Token is expiring soon, create new token
                    $newExpiresAt = $this->getTokenExpiresAt();
                    $newTokenResult = $user->createToken('auth_token', ['*'], $newExpiresAt);

                    // Delete old token
                    $currentToken->delete();

                    // Get the response
                    $response = $next($request);

                    // Add new token to response header
                    $response->headers->set('X-New-Token', $newTokenResult->plainTextToken);
                    $response->headers->set('X-Token-Expires-At', $newExpiresAt ? $newExpiresAt->toISOString() : null);
                    $response->headers->set('X-Token-Expires-In', $newExpiresAt ? $newExpiresAt->diffInSeconds($now) : null);

                    return $response;
                }
            }
        }

        return $next($request);
    }

    /**
     * Get token expiration time
     *
     * @return \Carbon\Carbon|null
     */
    private function getTokenExpiresAt(): ?Carbon
    {
        $expirationMinutes = config('sanctum.expiration');

        if ($expirationMinutes === null) {
            return null; // No expiration
        }

        return Carbon::now()->addMinutes((int) $expirationMinutes);
    }
}
