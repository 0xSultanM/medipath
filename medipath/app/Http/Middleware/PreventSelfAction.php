<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks destructive self-actions (suspend/delete own account, demote self).
 * The route parameter name must be one of: user, id.
 */
class PreventSelfAction
{
    public function handle(Request $request, Closure $next, string $parameter = 'user'): Response
    {
        $target = $request->route($parameter);
        $targetId = is_object($target) ? $target->getKey() : $target;

        if ($targetId !== null && (int) $targetId === (int) $request->user()?->id) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You cannot perform this action on your own account.'], 422);
            }

            return back()->with('error', 'You cannot perform this action on your own account.');
        }

        return $next($request);
    }
}
