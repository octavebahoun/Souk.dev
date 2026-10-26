<?php

namespace App\Jobs;

use App\Events\AnalyseIaTerminee;
use App\Ia\DemandeAnalyse;
use App\Ia\FournisseurIa;
use App\Ia\IaRefus;
use App\Models\AnalyseIa;
use App\Notifications\AlerteAdmin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Analyse Souk AI d'un bug, en file : une panne du fournisseur se réessaie deux fois, un refus
 * définitif s'affiche « analyse indisponible » tout de suite. Le résultat part par Reverb.
 */
class AnalyserBug implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60];

    public int $timeout = 200;

    public function __construct(public int $analyseId) {}

    public function handle(FournisseurIa $ia): void
    {
        $analyse = AnalyseIa::query()->with('discussion')->find($this->analyseId);
        if ($analyse === null || $analyse->statut !== 'en_cours' || $analyse->discussion === null) {
            return;
        }

        try {
            $reponse = $ia->analyser(DemandeAnalyse::consigne(), DemandeAnalyse::pour($analyse->discussion));
        } catch (IaRefus $refus) {
            if ($refus->concerneLEquipe()) {
                AlerteAdmin::envoyer('Souk AI indisponible', [$refus->getMessage(), 'Les analyses demandées échouent tant que ce n’est pas réglé.'], '/echange/'.$analyse->discussion_id);
            }
            $this->terminer($analyse, 'refus');

            return;
        }

        $analyse->forceFill([
            'statut' => 'prete',
            'texte' => $reponse->texte,
            'modele' => $reponse->modele,
            'jetons_entree' => $reponse->jetonsEntree,
            'jetons_sortie' => $reponse->jetonsSortie,
            'termine_le' => now(),
        ])->save();
        event(new AnalyseIaTerminee($analyse));
    }

    /** Plus d'essai possible (panne qui dure, délai dépassé) : « analyse indisponible ». */
    public function failed(?Throwable $erreur): void
    {
        Log::warning('souk-ai: analyse abandonnée', ['analyse' => $this->analyseId, 'erreur' => $erreur ? class_basename($erreur) : null]);
        $analyse = AnalyseIa::query()->find($this->analyseId);
        if ($analyse !== null && $analyse->statut === 'en_cours') {
            $this->terminer($analyse, 'indisponible');
        }
    }

    private function terminer(AnalyseIa $analyse, string $erreur): void
    {
        $analyse->forceFill(['statut' => 'echec', 'erreur' => $erreur, 'termine_le' => now()])->save();
        event(new AnalyseIaTerminee($analyse));
    }
}
