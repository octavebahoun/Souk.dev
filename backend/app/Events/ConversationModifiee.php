<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Une conversation change pour l'un de ses deux devs (invitation reçue, acceptée, déclinée,
 * nouveau message) : envoyée sur son canal privé, vue de son côté.
 */
final class ConversationModifiee implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation, public User $pour) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('utilisateur.'.$this->pour->id)];
    }

    public function broadcastAs(): string
    {
        return 'conversation.modifiee';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ConversationResource::pour($this->conversation, $this->pour);
    }
}
