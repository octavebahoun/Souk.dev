<?php

namespace App\Jobs;

use App\Models\CoordonneesReversement;
use App\Models\User;
use App\Paiement\GrandLivre;
use App\Paiement\Reversements;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Le 1er de chaque mois : chaque dev au solde positif, coordonnées confirmées, est reversé. */
class ReversementsMensuels implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Planifié : jamais deux passages en même temps (withoutOverlapping ne protège pas la file). */
    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue('paiements');
    }

    public function handle(Reversements $reversements, GrandLivre $grandLivre): void
    {
        $devs = CoordonneesReversement::query()->whereNotNull('confirme_le')->pluck('user_id');

        foreach (User::query()->whereIn('id', $devs)->get() as $dev) {
            if ($grandLivre->soldeDev($dev->id) <= 0 || $reversements->blocage($dev, 'mensuel') !== null) {
                continue;
            }

            try {
                $reversements->demander($dev, 'mensuel');
            } catch (Throwable $exception) {
                Log::error('reversement mensuel impossible', ['dev' => $dev->id, 'erreur' => $exception->getMessage()]);
            }
        }
    }
}
