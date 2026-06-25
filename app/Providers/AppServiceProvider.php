<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ✅ Rate Limiting للـ GitHub Login
        RateLimiter::for('github-login', function ($request) {
            // 5 محاولات في الدقيقة لكل IP
            return Limit::perMinute(5)->by($request->ip());
        });

        // ✅ Rate Limiting للـ Complete Profile
        RateLimiter::for('complete-profile', function ($request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
