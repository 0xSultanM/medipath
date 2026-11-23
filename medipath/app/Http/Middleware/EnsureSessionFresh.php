<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invalidates every session created before the user's most recent password
 * change, regardless of the session driver (Redis, database, file). This is the
 * cross-device revocation path used by password reset (PLAN Sprint 1.3).
 */
class EnsureSessionFresh
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $passwordMarker = $request->session()->get('auth.password_changed_at');
            $versionMarker = $request->session()->get('auth.session_version');

            $passwordStale = $user->password_changed_at
                && ($passwordMarker === null || (int) $passwordMarker !== $user->password_changed_at->getTimestamp());
            $versionStale = (int) $versionMarker !== (int) $user->session_version;

            if ($passwordStale || $versionStale) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Your password changed. Sign in again.',
                        'code' => 'session_revoked',
                        'retryable' => false,
                    ], 401);
                }

                return redirect()->route('login')
                    ->with('error', 'Your password was updated. Please sign in again.');
            }
        }

        return $next($request);
    }
}
