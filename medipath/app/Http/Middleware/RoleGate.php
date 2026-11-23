<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strict role gate (PLAN Sprint 0.2). Role comes from the authenticated DB
 * user — never from a route-provided id or client payload.
 */
class RoleGate
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Authentication required.'], 401)
                : redirect()->guest(route('login'));
        }

        if ($user->status !== AccountStatus::Approved || $user->archived_at !== null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This account is not active. Contact the clinic administrator.',
                    'code' => 'account_inactive',
                ], 403);
            }

            if (in_array($user->status, [AccountStatus::Pending, AccountStatus::Rejected], true)) {
                return redirect()->route('pending.approval');
            }

            return redirect()->route('login')
                ->with('error', 'This account is not active. Contact the clinic administrator.');
        }

        if ($roles !== [] && ! in_array($user->role?->value, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You do not have access to this area.'], 403);
            }

            abort(403, 'You do not have access to this area.');
        }

        return $next($request);
    }
}
