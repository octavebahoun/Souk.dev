<?php

namespace App\Jobs;

use App\Models\Deploiement;
use App\Moteur\CopieLocale;
use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\Exceptions\ManifestInvalide;
use App\Moteur\Exceptions\VariablesManquantes;
use App\Moteur\MoteurDeploiement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class LancerDeploiement implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $deploiementId) {}

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
                $deploiement->appli->depot_url,
                is_array($deploiement->variables) ? $deploiement->variables : [],
                'd'.$deploiement->id,
                true,
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
            $moteur->mettreEnLigne($copie);

            $enLigne = $this->estArrete($deploiement)
                ? false
                : $deploiement->changerEtat('en_ligne', url: str_replace(
                    '{copie}',
                    $copie->id,
                    (string) config('moteur.url_copie'),
                ));

            if (! $enLigne) {
                $this->couper($moteur, $copie);
            }
        } catch (Throwable $exception) {
            if ($this->estArrete($deploiement)) {
                if ($enDemarrage && $copie instanceof CopieLocale) {
                    $this->couper($moteur, $copie);
                }

                return;
            }

            $deploiement->changerEtat('echec', erreur: $this->messageLisible($exception));
        }
    }

    public function failed(Throwable $exception): void
    {
        $deploiement = Deploiement::query()->find($this->deploiementId);

        if ($deploiement === null || in_array($deploiement->etat, ['en_ligne', 'arrete', 'echec'], true)) {
            return;
        }

        $deploiement->changerEtat('echec', erreur: 'Le déploiement a échoué.');
    }

    private function estArrete(Deploiement $deploiement): bool
    {
        $deploiement->refresh();

        return $deploiement->etat === 'arrete';
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
