<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Holding screen guard: only pending accounts stay here. */
class PendingStatusOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->status?->value === 'approved') {
            return redirect()->route($user->dashboard_route);
        }

        // Pending and rejected applicants both authenticate to this holding
        // screen so they can track the decision and see the rejection reason.
        if (! in_array($user->status?->value, ['pending', 'rejected'], true)) {
            return redirect()->route('login')
                ->with('error', 'This account cannot access the approval status screen.');
        }

        return $next($request);
    }
}
