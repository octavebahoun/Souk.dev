<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Discussion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

final class DiscussionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Discussion $discussion */
        $discussion = $this->resource;
        $discussion->loadMissing('auteur', 'canal', 'derniereAnalyse', 'dernierePrete');

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
            'copie_test' => $discussion->copieTest?->versApi(),
            'analyse_ia' => $discussion->analyseAffichee()?->versApi(),
            'resolue' => $discussion->resolue,
            'nb_messages' => $discussion->messages_count ?? $discussion->messages()->count(),
            'cree_le' => $discussion->created_at?->toIso8601String(),
        ];
    }

    /**
     * Charge utile des événements Reverb : texte abrégé, analyse sans son texte. Reverb refuse
     * les messages de plus de 10 Ko ; la page relit la discussion complète par l'API.
     *
     * @return array<string, mixed>
     */
    public static function pourDiffusion(Discussion $discussion): array
    {
        $donnees = (new self($discussion))->resolve();
        $donnees['texte'] = Str::limit((string) $donnees['texte'], 500);
        if (is_array($donnees['analyse_ia'])) {
            $donnees['analyse_ia']['texte'] = null;
        }
        if (is_array($donnees['bug']) && is_string($donnees['bug']['code'] ?? null)) {
            $donnees['bug']['code'] = Str::limit($donnees['bug']['code'], 500);
        }

        return $donnees;
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
