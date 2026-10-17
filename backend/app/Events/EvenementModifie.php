<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\EvenementResource;
use App\Models\Evenement;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EvenementModifie implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Evenement $evenement) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('evenement.'.$this->evenement->id)];
    }

    public function broadcastAs(): string
    {
        return 'evenement.modifie';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $payload = (new EvenementResource($this->evenement))->resolve();
        $payload['lien'] = null;

        return $payload;
    }
}
