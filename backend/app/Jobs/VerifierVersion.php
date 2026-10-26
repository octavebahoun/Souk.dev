<?php

namespace App\Jobs;

use App\Models\Deploiement;
use App\Models\VersionAppli;
use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\Exceptions\ManifestInvalide;
use App\Moteur\PublicationDepot;
use App\Notifications\NouvelleVersion;
use App\Securite\AnalyseurDepot;
use App\Securite\VerificationSecrets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Une nouvelle version passe les mêmes contrôles qu'une publication, sur le commit demandé :
 * soukdev.json, docker-compose.yml, aucun secret. Publiée, elle est proposée aux clients (ils
 * choisissent quand l'appliquer) ; refusée, l'auteur lit pourquoi. Sur la file « secrets » :
 * son worker a Git et Docker, et une vérification n'attend pas derrière une analyse Trivy.
 */
class VerifierVersion implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 700;

    public function __construct(public int $versionId)
    {
        $this->onQueue('secrets');
    }

    public function handle(PublicationDepot $depot, AnalyseurDepot $analyseur): void
    {
        $version = VersionAppli::query()->with('appli')->find($this->versionId);
        if ($version === null || $version->statut !== 'verification' || $version->appli === null) {
            return;
        }

        try {
            $lu = $depot->verifier((string) $version->appli->depot_url, $version->ref, function (string $repertoire) use ($analyseur): void {
                $secrets = $analyseur->secrets($repertoire);
                if ($secrets !== []) {
                    throw new DepotInvalide(VerificationSecrets::message($secrets));
                }
            });
        } catch (DepotInvalide|ManifestInvalide|LancementEchoue $refus) {
            $version->forceFill(['statut' => 'refusee', 'erreur' => $refus->getMessage()])->save();

            return;
        } catch (Throwable $erreur) {
            report($erreur);
            $version->forceFill(['statut' => 'refusee', 'erreur' => 'La version n’a pas pu être vérifiée. Réessayez dans quelques minutes.'])->save();

            return;
        }

        $version->forceFill(['statut' => 'publiee', 'commit' => $lu['commit'], 'manifeste' => $lu['manifeste'], 'erreur' => null])->save();
        Log::info('versions: version publiée', ['appli' => $version->appli_id, 'numero' => $version->numero]);

        // Le badge de sécurité suit la dernière version ; les clients des copies actives sont prévenus.
        $version->appli->demanderAnalyseSecurite();
        Deploiement::query()
            ->with('utilisateur')
            ->where('appli_id', $version->appli_id)
            ->where('type', 'client')
            ->whereIn('etat', ['en_ligne', 'suspendu'])
            ->get()
            ->each(fn (Deploiement $copie) => $copie->utilisateur?->notify(new NouvelleVersion($version, $copie)));
    }
}
