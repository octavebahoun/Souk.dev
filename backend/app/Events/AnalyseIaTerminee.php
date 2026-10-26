<?php

namespace App\Events;

use App\Models\AnalyseIa;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** L'analyse Souk AI d'une discussion est prête ou a échoué : la page la montre sans recharger. */
class AnalyseIaTerminee implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public AnalyseIa $analyse) {}

    public function broadcastOn(): Channel
    {
        return new Channel('discussion.'.$this->analyse->discussion_id);
    }

    public function broadcastAs(): string
    {
        return 'analyse_ia.terminee';
    }

    /** @return array{statut: string, texte: string|null, cree_le: string|null} */
    public function broadcastWith(): array
    {
        return $this->analyse->versApi();
    }
}
