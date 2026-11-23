<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pharmacist portal requires an approved account + pharmacist profile. */
class ApprovedPharmacist
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->role?->value !== 'pharmacist' || $user?->pharmacist === null || ! $user->isActive()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Pharmacist approval is required.',
                    'code' => 'pharmacist_unapproved',
                ], 403);
            }

            abort(403, 'Pharmacist approval is required.');
        }

        return $next($request);
    }
}
