<?php

namespace App\Jobs;

use App\Models\Deploiement;
use App\Moteur\CopieLocale;
use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\Exceptions\ManifestInvalide;
use App\Moteur\Exceptions\VariablesManquantes;
use App\Moteur\MoteurDeploiement;
use App\Moteur\NettoyeurCopie;
use App\Paiement\Facturation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class LancerDeploiement implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $deploiementId)
    {
        $this->onQueue('moteur');
    }

    public function handle(MoteurDeploiement $moteur): void
    {
        $deploiement = Deploiement::query()->find($this->deploiementId);

        if ($deploiement === null || $deploiement->etat !== 'en_file') {
            return;
        }

        $enDemarrage = false;
        $copie = null;

        try {
            if (! $deploiement->changerEtat('construction')) {
                return;
            }

            if ($this->estArrete($deploiement)) {
                return;
            }

            $copie = $moteur->depuisGithub(
                $deploiement->depotACloner(),
                is_array($deploiement->variables) ? $deploiement->variables : [],
                'd'.$deploiement->id,
                true,
                commit: $deploiement->version?->commit,
            );

            $deploiement->copie = $copie->id;
            $deploiement->repertoire = $copie->repertoire;
            $deploiement->save();

            if ($this->estArrete($deploiement)) {
                return;
            }

            if (! $deploiement->changerEtat('demarrage')) {
                return;
            }

            $enDemarrage = true;
            $moteur->mettreEnLigne($copie, (string) $deploiement->taille);

            $enLigne = $this->estArrete($deploiement)
                ? false
                : $deploiement->changerEtat('en_ligne', url: str_replace(
                    '{copie}',
                    $copie->id,
                    (string) config('moteur.url_copie'),
                ));

            if (! $enLigne) {
                $this->couper($moteur, $copie);
            } else {
                app(Facturation::class)->leverIncident($deploiement);
            }
        } catch (Throwable $exception) {
            if ($this->estArrete($deploiement)) {
                if ($enDemarrage && $copie instanceof CopieLocale) {
                    $this->couper($moteur, $copie);
                }

                return;
            }

            $deploiement->changerEtat('echec', erreur: $this->messageLisible($exception));

            // Copie payée qui ne démarre pas : plus de facture, l'équipe et le client sont prévenus.
            if ($deploiement->type === 'client') {
                app(Facturation::class)->signalerIncident($deploiement, $this->messageLisible($exception));
            }

            // Conteneurs, volumes et dossier cloné (avec son .env) ne restent pas après un échec.
            app(NettoyeurCopie::class)->liberer($copie?->id ?? $deploiement->copie, $copie?->repertoire ?? $deploiement->repertoire, $deploiement->id);
        }
    }

    public function failed(Throwable $exception): void
    {
        $deploiement = Deploiement::query()->find($this->deploiementId);

        if ($deploiement === null || in_array($deploiement->etat, ['en_ligne', 'arrete', 'echec'], true)) {
            return;
        }

        $deploiement->changerEtat('echec', erreur: 'Le déploiement a échoué.');

        if ($deploiement->type === 'client') {
            app(Facturation::class)->signalerIncident($deploiement, 'Le déploiement a échoué ('.$exception->getMessage().').');
        }
    }

    private function estArrete(Deploiement $deploiement): bool
    {
        $deploiement->refresh();

        return $deploiement->estEnCoursArret();
    }

    private function couper(MoteurDeploiement $moteur, CopieLocale $copie): void
    {
        try {
            $moteur->arreter($copie);
        } catch (Throwable) {
            // La copie est déjà coupée.
        }
    }

    private function messageLisible(Throwable $exception): string
    {
        if ($exception instanceof LancementEchoue
            || $exception instanceof ManifestInvalide
            || $exception instanceof DepotInvalide
            || $exception instanceof VariablesManquantes) {
            return $exception->getMessage();
        }

        return 'Le déploiement a échoué.';
    }
}
