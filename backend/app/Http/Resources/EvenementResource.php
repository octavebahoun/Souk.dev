<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Evenement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EvenementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Evenement $evenement */
        $evenement = $this->resource;
        $evenement->loadMissing('organisateur');

        $inscrit = $this->inscrit($evenement, $request);
        $organisateur = $request->user() !== null && $request->user()->id === $evenement->user_id;

        return [
            'id' => $evenement->id,
            'titre' => $evenement->titre,
            'description' => $evenement->description,
            'date' => $evenement->date?->toIso8601String(),
            'type' => $evenement->type,
            'lieu' => $evenement->lieu,
            'lien' => ($inscrit || $organisateur) ? $evenement->lien : null,
            'organisateur' => DevResource::champs($evenement->organisateur),
            'nb_inscrits' => (int) ($evenement->inscriptions_count ?? $evenement->inscriptions()->count()),
            'inscrit' => $inscrit,
            'note_moyenne' => $this->noteMoyenne($evenement),
        ];
    }

    private function inscrit(Evenement $evenement, Request $request): bool
    {
        if (array_key_exists('est_inscrit', $evenement->getAttributes())) {
            return (bool) $evenement->est_inscrit;
        }

        $compte = $request->user();

        return $compte !== null && $evenement->inscriptions()->where('user_id', $compte->id)->exists();
    }

    private function noteMoyenne(Evenement $evenement): ?float
    {
        if (array_key_exists('avis_avg_note', $evenement->getAttributes())) {
            $moyenne = $evenement->getAttribute('avis_avg_note');

            return $moyenne === null ? null : round((float) $moyenne, 2);
        }

        $moyenne = $evenement->avis()->avg('note');

        return $moyenne === null ? null : round((float) $moyenne, 2);
    }
}
