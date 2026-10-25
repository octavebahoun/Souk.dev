<?php

declare(strict_types=1);

namespace App\Reputation;

use App\Models\Appli;
use App\Models\Correctif;
use App\Models\Evenement;
use App\Models\Mission;
use App\Models\User;

/**
 * Rassemble depuis la base ce qu'un dev a fait de validé par quelqu'un d'autre :
 * correctifs acceptés, applis publiées (et leur badge), missions notées, événements passés.
 */
final readonly class ConstruireBilan
{
    /** Fenêtre de l'activité récente du Souk Score. */
    private const JOURS_RECENTS = 30;

    public function pour(User $dev): BilanDev
    {
        $depuis = now()->subDays(self::JOURS_RECENTS);

        $correctifs = Correctif::query()
            ->where('user_id', $dev->id)
            ->where('accepte', true)
            ->get(['accepte_le']);

        $applis = Appli::query()
            ->where('user_id', $dev->id)
            ->get(['securite_statut', 'created_at']);

        // Le dev est noté comme auteur de la mission, jamais comme client.
        $missions = Mission::query()
            ->where('auteur_id', $dev->id)
            ->where('statut', 'terminee')
            ->get(['note', 'terminee_le']);

        // Un événement annulé est supprimé : ceux qui restent ont eu lieu.
        $evenements = Evenement::query()
            ->where('user_id', $dev->id)
            ->where('date', '<=', now())
            ->get(['date']);

        $recents = $correctifs->filter(fn (Correctif $correctif) => $correctif->accepte_le?->greaterThanOrEqualTo($depuis) ?? false)->count()
            + $applis->filter(fn (Appli $appli) => $appli->created_at?->greaterThanOrEqualTo($depuis) ?? false)->count()
            + $missions->filter(fn (Mission $mission) => $mission->terminee_le?->greaterThanOrEqualTo($depuis) ?? false)->count()
            + $evenements->filter(fn (Evenement $evenement) => $evenement->date->greaterThanOrEqualTo($depuis))->count();

        return new BilanDev(
            bugsResolus: $correctifs->count(),
            applisPubliees: $applis->count(),
            applisSecurisees: $applis->where('securite_statut', 'verifie')->count(),
            notesMissions: $missions->pluck('note')->map(fn ($note) => (int) $note)->values()->all(),
            evenementsPasses: $evenements->count(),
            actionsRecentes: $recents,
        );
    }
}
