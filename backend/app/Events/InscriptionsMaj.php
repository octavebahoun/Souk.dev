<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InscriptionsMaj implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $evenementId, public int $nbInscrits) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('evenement.'.$this->evenementId)];
    }

    public function broadcastAs(): string
    {
        return 'inscriptions.maj';
    }

    /**
     * @return array{nb_inscrits: int}
     */
    public function broadcastWith(): array
    {
        return ['nb_inscrits' => $this->nbInscrits];
    }
}
