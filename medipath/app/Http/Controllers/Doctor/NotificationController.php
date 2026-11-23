<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Doctor notification centre (PLAN Sprint 7.4). */
class NotificationController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';
        $user = $request->user();
        $base = Notification::query()->where('user_id', $user->id);

        $rows = (clone $base)
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $forced = $this->state($request);
        $state = $forced ?? ($rows->total() === 0 ? 'empty' : 'success');

        return Inertia::render('Doctor/Notifications', [
            'notifications' => $rows->getCollection()->map(fn (Notification $notification): array => [
                'id' => $notification->id,
                'category' => $notification->category,
                'title' => $notification->title,
                'message' => $notification->message,
                'actionUrl' => $notification->action_url,
                'readAt' => $notification->read_at?->toIso8601String(),
                'createdAt' => $notification->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'filter' => $filter,
            'counts' => ['all' => (clone $base)->count(), 'unread' => (clone $base)->whereNull('read_at')->count()],
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    private function state(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}