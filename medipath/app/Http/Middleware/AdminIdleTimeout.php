<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administrative session idle timeout (PLAN Sprint 2.1). Every admin request
 * refreshes a server-side timestamp; inactivity beyond the configured window
 * destroys the session and returns the admin to login.
 */
class AdminIdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'admin.last_activity';
        $timeout = app(SettingsService::class)->adminIdleMinutes() * 60;
        $last = $request->session()->get($key);

        if ($last !== null && (time() - (int) $last) >= $timeout) {
            $user = $request->user();

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Admin session expired after inactivity. Sign in again.',
                    'code' => 'admin_idle_timeout',
                    'retryable' => false,
                ], 401);
            }

            return redirect()->route('login')
                ->with('error', 'Your admin session expired after inactivity. Sign in again.');
        }

        $request->session()->put($key, time());

        return $next($request);
    }
}