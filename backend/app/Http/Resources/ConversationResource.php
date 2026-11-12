<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** Une conversation vue par l'un de ses deux devs : l'autre personne, où en est l'invitation, ce qu'il reste à lire. */
final class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public static function pour(Conversation $conversation, User $compte): array
    {
        $conversation->loadMissing(['demandeur', 'destinataire', 'discussion', 'dernierMessage']);
        $autre = $conversation->autre($compte);
        $dernier = $conversation->dernierMessage;

        return [
            'id' => $conversation->id,
            'statut' => $conversation->statut,
            // « recue » : c'est à ce compte d'accepter ou de décliner.
            'sens' => $conversation->destinataire_id === $compte->id ? 'recue' : 'envoyee',
            'avec' => $autre === null ? null : DevResource::champs($autre),
            'invitation' => $conversation->invitation,
            'discussion' => $conversation->discussion === null ? null : [
                'id' => $conversation->discussion->id,
                'titre' => $conversation->discussion->titre,
            ],
            'dernier_message' => $dernier === null ? null : [
                'texte' => Str::limit($dernier->texte, 140),
                'de_moi' => $dernier->auteur_id === $compte->id,
                'cree_le' => $dernier->created_at?->toIso8601String(),
            ],
            'non_lus' => $conversation->estAcceptee() ? $conversation->nonLus($compte) : 0,
            'cree_le' => $conversation->created_at?->toIso8601String(),
            'maj_le' => ($conversation->dernier_message_le ?? $conversation->repondu_le ?? $conversation->created_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $compte */
        $compte = $request->user();

        return self::pour($this->resource, $compte);
    }
}
