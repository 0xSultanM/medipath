<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Models\PendingApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Pending / rejected application holding screen (PLAN Sprint 1.8). */
class PendingApprovalController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $application = PendingApproval::query()->where('user_id', $user->id)->latest()->first();

        $forced = (string) $request->query('state', '');
        $state = in_array($forced, ['success', 'skeleton', 'empty', 'error'], true)
            ? $forced
            : ($application ? 'success' : 'empty');

        return Inertia::render('Guest/PendingApproval', [
            'application' => $this->applicationPayload($request),
            'dataState' => $state,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            ...$this->applicationPayload($request),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    private function applicationPayload(Request $request): array
    {
        $user = $request->user();
        $application = PendingApproval::query()->where('user_id', $user->id)->latest()->first();

        $queuePosition = null;
        $totalPending = PendingApproval::query()->pending()->count();

        if ($application?->status === 'pending') {
            $queuePosition = PendingApproval::query()
                ->pending()
                ->where(function ($query) use ($application) {
                    $query->where('created_at', '<', $application->created_at)
                        ->orWhere(function ($inner) use ($application) {
                            $inner->where('created_at', $application->created_at)
                                ->where('id', '<=', $application->id);
                        });
                })
                ->count();
        }

        return [
            'state' => match ($user->status) {
                AccountStatus::Approved => 'approved',
                AccountStatus::Rejected => 'rejected',
                AccountStatus::Pending => $application ? 'pending' : 'none',
                default => 'none',
            },
            'status' => $user->status?->value,
            'statusLabel' => $user->status?->label(),
            'requestedRole' => $application?->requested_role,
            'requestedRoleLabel' => config('medipath.roles.'.$application?->requested_role.'.label'),
            'queuePosition' => $queuePosition,
            'totalPending' => $totalPending,
            'reviewReason' => $application?->review_reason,
            'submittedAt' => $application?->created_at?->toIso8601String(),
            'reviewedAt' => $application?->reviewed_at?->toIso8601String(),
            'dashboardUrl' => route($user->dashboard_route),
            'signOutUrl' => route('logout'),
        ];
    }
}
