<?php

declare(strict_types=1);

namespace App\Reputation;

use App\Models\Mission;
use App\Models\User;

/**
 * Rassemble depuis la base ce qu'un dev a fait de validé.
 * Pour l'instant : les missions. Les correctifs, les applis et les événements
 * s'ajouteront quand leurs tables arriveront sur main.
 */
final readonly class ConstruireBilan
{
    /** Fenêtre de l'activité récente du Souk Score. */
    private const JOURS_RECENTS = 30;

    public function pour(User $dev): BilanDev
    {
        // Le dev est noté comme auteur de la mission, jamais comme client.
        $missions = Mission::query()
            ->where('auteur_id', $dev->id)
            ->where('statut', 'terminee')
            ->get(['note', 'terminee_le']);

        $depuis = now()->subDays(self::JOURS_RECENTS);

        return new BilanDev(
            notesMissions: $missions->pluck('note')->map(fn ($note) => (int) $note)->values()->all(),
            actionsRecentes: $missions->filter(fn (Mission $mission) => $mission->terminee_le?->greaterThanOrEqualTo($depuis) ?? false)->count(),
        );
    }
}
