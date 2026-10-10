<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Discussion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DiscussionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Discussion $discussion */
        $discussion = $this->resource;
        $discussion->loadMissing('auteur', 'canal');

        return [
            'id' => $discussion->id,
            'titre' => $discussion->titre,
            'texte' => $discussion->texte,
            'auteur' => DevResource::champs($discussion->auteur),
            'canal' => $discussion->canal?->slug,
            'app_id' => $discussion->app_id,
            'depot_url' => $discussion->depot_url,
            'demo_url' => $discussion->demo_url,
            'etiquettes' => array_values($discussion->etiquettes ?? []),
            'bug' => $this->bug($discussion),
            'copie_test' => null,
            'analyse_ia' => null,
            'resolue' => $discussion->resolue,
            'nb_messages' => $discussion->messages_count ?? $discussion->messages()->count(),
            'cree_le' => $discussion->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bug(Discussion $discussion): ?array
    {
        $etiquettes = $discussion->etiquettes ?? [];

        if (! in_array('bug', $etiquettes, true) || ! is_array($discussion->bug)) {
            return null;
        }

        return [
            'erreur_obtenue' => (string) ($discussion->bug['erreur_obtenue'] ?? ''),
            'comportement_attendu' => (string) ($discussion->bug['comportement_attendu'] ?? ''),
            'techno' => $discussion->bug['techno'] ?? null,
            'code' => $discussion->bug['code'] ?? null,
            'difficulte' => $discussion->bug['difficulte'] ?? null,
        ];
    }
}
