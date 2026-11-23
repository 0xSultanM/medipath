<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Append-only audit trail (PLAN Sprint 0.4). The service is the only writer;
 * ActivityLog refuses updates/deletes at the model layer and there are no
 * update/delete routes (G8).
 */
class ActivityLogService
{
    public function record(
        string $action,
        Model|array|null $target = null,
        array $detail = [],
        ?User $actor = null,
    ): ActivityLog {
        $actor ??= Auth::user();

        [$targetType, $targetId] = $this->resolveTarget($target);

        return ActivityLog::query()->create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role?->value,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'detail' => $detail ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    /** @return array{0:?string,1:?int} */
    private function resolveTarget(Model|array|null $target): array
    {
        if ($target instanceof Model) {
            return [$target->getMorphClass(), $target->getKey()];
        }

        if (is_array($target)) {
            return [$target['type'] ?? null, $target['id'] ?? null];
        }

        return [null, null];
    }
}
