<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        parent::boot();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // LOGIN
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                strtolower($request->input('email')) . '|' . $request->ip()
            );
        });

        // OTP / RESET PASSWORD
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinutes(5, 3)->by($request->ip());
        });

        // REFRESH TOKEN
        RateLimiter::for('refresh-token', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // API AUTHENTICATED USER
        RateLimiter::for('api-user', function (Request $request) {
            return Limit::perMinute(120)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });

        // REPORTS
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()->id);
        });
    }
}
