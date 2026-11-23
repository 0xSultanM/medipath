<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('chatbot', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(
            (int) config('medipath.registration.max_per_hour', 10)
        )->by($request->ip()));

        RateLimiter::for('forgot-password', fn (Request $request) => Limit::perMinute(3)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        ));

        RateLimiter::for('reset-password', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        ));

        RateLimiter::for('pending-status', fn (Request $request) => Limit::perMinute(30)->by(
            (string) ($request->user()?->id ?? $request->ip())
        ));
    }
}
