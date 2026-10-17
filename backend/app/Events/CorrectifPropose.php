<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\CorrectifResource;
use App\Models\Correctif;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CorrectifPropose implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Correctif $correctif) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('discussion.'.$this->correctif->discussion_id)];
    }

    public function broadcastAs(): string
    {
        return 'correctif.propose';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return (new CorrectifResource($this->correctif))->resolve();
    }
}
