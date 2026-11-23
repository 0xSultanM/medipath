<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * User notifications: DB persistence + Reverb broadcast (PLAN Sprint 0.4).
 * The bell/panel reads through this service so unread counts are always
 * server-authoritative.
 */
class NotificationService
{
    public function send(
        User $user,
        string $type,
        string $title,
        string $message,
        string $category = 'system',
        array $data = [],
        ?string $actionUrl = null,
    ): Notification {
        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'data' => $data ?: null,
            'action_url' => $actionUrl,
        ]);

        broadcast(new NotificationCreated($notification))->toOthers();

        return $notification;
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()->where('user_id', $user->id)->whereNull('read_at')->count();
    }

    /** @return Collection<int, Notification> */
    public function recent(User $user, int $limit = 8): Collection
    {
        return Notification::query()->where('user_id', $user->id)
            ->latest()->limit($limit)->get();
    }

    public function markRead(User $user, int $notificationId): bool
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->whereKey($notificationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]) > 0;
    }

    public function markAllRead(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
