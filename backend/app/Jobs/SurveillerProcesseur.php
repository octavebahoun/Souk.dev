<?php

namespace App\Jobs;

use App\Models\Deploiement;
use App\Models\Machine;
use App\Moteur\LimitesTaille;
use App\Notifications\AlerteAdmin;
use App\Securite\SuspensionAbus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Politique d'abus, minage : toutes les 5 minutes, le processeur des copies en ligne est relevé,
 * machine par machine (une tâche chacune : une machine lente ne retient pas les autres). Une copie
 * dont un conteneur reste à 90 % ou plus de son plafond pendant une heure d'affilée (12 relevés
 * de suite) est suspendue pour minage présumé ; l'équipe est prévenue et peut la réactiver.
 * Un relevé sous le seuil, ou manqué (machine muette), remet le compte à zéro.
 */
class SurveillerProcesseur implements ShouldQueue
{
    use Queueable;

    public const SEUIL = 0.9;

    public const RELEVES = 12;

    public int $tries = 1;

    public function __construct(public ?int $machineId = null)
    {
        // Comme la sonde des machines : le service « sondes » a le socket Docker.
        $this->onQueue('sondes');
    }

    public function handle(SuspensionAbus $suspension): void
    {
        if ($this->machineId === null) {
            foreach (Machine::query()->where('joignable', true)->orderBy('id')->pluck('id') as $id) {
                self::dispatch($id);
            }

            return;
        }

        $machine = Machine::query()->find($this->machineId);
        $mesures = $machine?->joignable ? $machine->cible()->processeurParCopie() : null;

        if ($machine !== null && $mesures !== null) {
            $copies = Deploiement::query()
                ->where('machine_id', $machine->id)
                ->where('etat', 'en_ligne')
                ->whereNull('suspension_admin_motif')
                ->get();

            foreach ($copies as $deploiement) {
                $this->relever($deploiement, $mesures[$deploiement->copie] ?? 0.0, $suspension);
            }
        }
    }

    private function relever(Deploiement $deploiement, float $coeurs, SuspensionAbus $suspension): void
    {
        $cle = "processeur-sature:{$deploiement->id}";

        if (! LimitesTaille::connue((string) $deploiement->taille) || $coeurs < self::SEUIL * LimitesTaille::coeurs((string) $deploiement->taille)) {
            Cache::forget($cle);

            return;
        }

        // 15 minutes de vie : un relevé manqué entre deux passages remet le compte à zéro.
        $releves = (int) Cache::get($cle, 0) + 1;
        Cache::put($cle, $releves, now()->addMinutes(15));

        if ($releves < self::RELEVES) {
            return;
        }

        Cache::forget($cle);
        $motif = 'Minage présumé : processeur saturé pendant plus d’une heure.';

        if ($suspension->suspendre($deploiement, $motif) !== SuspensionAbus::SUSPENDUE) {
            return;
        }

        Log::warning('moderation: copie suspendue pour minage présumé', ['deploiement' => $deploiement->id, 'coeurs' => $coeurs]);
        AlerteAdmin::envoyer("Copie #{$deploiement->id} suspendue : minage présumé", [
            "La copie {$deploiement->copie} a un conteneur à plus de 90 % de son processeur depuis une heure : elle est suspendue (politique d’abus).",
            'Vérifiez et réactivez-la depuis l’administration si c’est une charge légitime.',
        ], '/admin/moderation');
    }
}
