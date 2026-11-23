<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\ActivityActions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Immutable activity log viewer (admin/09, Sprint 3.5). */
class ActivityController extends Controller
{
    /** @var array<int, string> */
    private const WARNING_ACTIONS = [
        ActivityActions::AUTH_LOGIN_FAILED,
        ActivityActions::USER_SUSPENDED,
        ActivityActions::USER_ARCHIVED,
        ActivityActions::APPROVAL_REJECTED,
        ActivityActions::DOCTOR_RESUBMISSION_REQUESTED,
        ActivityActions::DOCTOR_VERIFICATION_REVOKED,
        ActivityActions::SETTINGS_UPDATED,
        ActivityActions::REVENUE_CONFIGURED,
        ActivityActions::BACKUP_DELETED,
        ActivityActions::SECURITY_PASSWORD_CHANGED,
        ActivityActions::SECURITY_SESSIONS_REVOKED,
    ];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $role = (string) $request->query('actor_role', '');
        $severity = (string) $request->query('severity', '');
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        $rows = ActivityLog::query()
            ->with('actor:id,name,role')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.mb_strtolower(str_replace(['%', '_'], ['\\%', '\\_'], $q)).'%';
                $query->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(action) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(target_type) LIKE ?', [$like])
                        ->orWhereRaw('CAST(target_id AS CHAR) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(CAST(detail AS CHAR)) LIKE ?', [$like]);
                });
            })
            ->when(in_array($role, ['admin', 'doctor', 'pharmacist', 'patient', 'system'], true), function ($query) use ($role) {
                $role === 'system' ? $query->whereNull('actor_id') : $query->where('actor_role', $role);
            })
            ->when($severity === 'critical', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('action', ActivityActions::EMERGENCY_RULE_MATCHED)
                ->orWhere('action', 'like', 'emergency.%')))
            ->when($severity === 'warning', fn ($query) => $query->whereIn('action', self::WARNING_ACTIONS))
            ->when($severity === 'notice', fn ($query) => $query
                ->where('action', '!=', ActivityActions::EMERGENCY_RULE_MATCHED)
                ->where('action', 'not like', 'emergency.%')
                ->whereNotIn('action', self::WARNING_ACTIONS))
            ->when($from !== '', fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $forced = (string) $request->query('state', '');
        $state = in_array($forced, ['success', 'skeleton', 'empty', 'error'], true)
            ? $forced
            : ($rows->total() === 0 ? 'empty' : 'success');

        return Inertia::render('Admin/ActivityLog', [
            'entries' => $rows->getCollection()->map(fn (ActivityLog $entry): array => [
                'id' => $entry->id,
                'createdAt' => $entry->created_at?->toIso8601String(),
                'actorName' => $entry->actor?->name ?? 'system',
                'actorRole' => $entry->actor?->role?->value ?? $entry->actor_role,
                'actorRoleLabel' => $entry->actor?->role?->label() ?? ($entry->actor_role ? config("medipath.roles.{$entry->actor_role}.label") : 'System'),
                'action' => $entry->action,
                'targetType' => $entry->target_type,
                'targetId' => $entry->target_id,
                'severity' => $this->severityFor((string) $entry->action),
                'detail' => $entry->detail,
                'ipAddress' => $entry->ip_address,
            ])->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'filters' => ['q' => $q, 'actor_role' => $role, 'severity' => $severity, 'from' => $from, 'to' => $to],
            'roles' => collect(config('medipath.roles'))->map(fn ($r) => $r['label'])->all(),
            'severities' => ['notice' => 'Notice', 'warning' => 'Warning', 'critical' => 'Critical (emergency)'],
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    private function severityFor(string $action): string
    {
        if ($action === ActivityActions::EMERGENCY_RULE_MATCHED || str_starts_with($action, 'emergency.')) {
            return 'critical';
        }

        return in_array($action, self::WARNING_ACTIONS, true) ? 'warning' : 'notice';
    }
}