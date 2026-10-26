<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\FactureResource;
use App\Models\Facture;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Une facture du compte change (payée après vérification, annulée, remboursée…). */
final class FactureModifiee implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Facture $facture) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('utilisateur.'.$this->facture->client_id)];
    }

    public function broadcastAs(): string
    {
        return 'facture.modifiee';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return (new FactureResource($this->facture))->resolve();
    }
}
