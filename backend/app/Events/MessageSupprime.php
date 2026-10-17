<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class MessageSupprime implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $id, public int $discussionId) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('discussion.'.$this->discussionId)];
    }

    public function broadcastAs(): string
    {
        return 'message.supprime';
    }

    /**
     * @return array{id: int}
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->id];
    }
}
