<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\DiscussionResource;
use App\Models\Discussion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DiscussionCreee implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Discussion $discussion) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('discussions')];
    }

    public function broadcastAs(): string
    {
        return 'discussion.creee';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->discussion->loadCount('messages');

        return (new DiscussionResource($this->discussion))->resolve();
    }
}
