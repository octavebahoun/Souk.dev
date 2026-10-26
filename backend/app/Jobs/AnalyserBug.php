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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Analyse Souk AI d'un bug, sur sa propre file « ia » (jamais devant les paiements) : une panne
 * du fournisseur se réessaie une fois, un refus s'affiche « analyse indisponible » tout de suite.
 * Un résultat arrivé après une modification de la fiche est écarté. Le résultat part par Reverb.
 */
class AnalyserBug implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [30];

    /** L'appel HTTP s'arrête à 90 secondes ; le travail, un peu après. */
    public int $timeout = 120;

    public function __construct(public int $analyseId)
    {
        $this->onQueue('ia');
    }

    public function handle(FournisseurIa $ia): void
    {
        $analyse = AnalyseIa::query()->with('discussion')->find($this->analyseId);
        if ($analyse === null || $analyse->statut !== 'en_cours') {
            return;
        }
        if ($analyse->discussion === null) {
            $this->terminer($analyse, 'discussion_supprimee');

            return;
        }

        $borne = DemandeAnalyse::borne();
        try {
            $reponse = $ia->analyser(DemandeAnalyse::consigne($borne), DemandeAnalyse::pour($analyse->discussion, $borne));
        } catch (IaRefus $refus) {
            // Une alerte par heure au plus : une clé refusée ne doit pas inonder la boîte de l'équipe.
            if ($refus->concerneLEquipe() && Cache::add('souk-ai:alerte-equipe', true, now()->addHour())) {
                AlerteAdmin::envoyer('Souk AI indisponible', [$refus->getMessage(), 'Les analyses demandées échouent tant que ce n’est pas réglé (une alerte par heure au plus).'], '/echange/'.$analyse->discussion_id);
            }
            $this->terminer($analyse, 'refus');

            return;
        }

        // Écrite seulement si elle est toujours en cours : une fiche modifiée entre-temps l'a périmée.
        $ecrite = AnalyseIa::query()->whereKey($analyse->id)->where('statut', 'en_cours')->update([
            'statut' => 'prete',
            'texte' => $reponse->texte,
            'modele' => $reponse->modele,
            'jetons_entree' => $reponse->jetonsEntree,
            'jetons_sortie' => $reponse->jetonsSortie,
            'termine_le' => now(),
        ]);
        if ($ecrite > 0) {
            event(new AnalyseIaTerminee($analyse->refresh()));
        }
    }

    /** Plus d'essai possible (panne qui dure, délai dépassé) : « analyse indisponible ». */
    public function failed(?Throwable $erreur): void
    {
        Log::warning('souk-ai: analyse abandonnée', ['analyse' => $this->analyseId, 'erreur' => $erreur ? class_basename($erreur) : null]);
        $analyse = AnalyseIa::query()->find($this->analyseId);
        if ($analyse !== null) {
            $this->terminer($analyse, 'indisponible');
        }
    }

    private function terminer(AnalyseIa $analyse, string $erreur): void
    {
        // Une analyse périmée entre-temps reste périmée.
        $modifiees = AnalyseIa::query()->whereKey($analyse->id)->where('statut', 'en_cours')
            ->update(['statut' => 'echec', 'erreur' => $erreur, 'termine_le' => now()]);
        if ($modifiees > 0) {
            event(new AnalyseIaTerminee($analyse->refresh()));
        }
    }
}
