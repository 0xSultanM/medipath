<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Broadcast after an Admin changes a system setting (currency/theme/etc.). */
class SettingsChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly ?int $actorId = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('admin.settings');
    }

    public function broadcastAs(): string
    {
        return 'settings.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'actor_id' => $this->actorId,
        ];
    }
}
