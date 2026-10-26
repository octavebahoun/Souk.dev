<?php

namespace App\Jobs;

use App\Models\MiseAJour;
use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\Exceptions\ManifestInvalide;
use App\Moteur\Exceptions\MiseAJourRetablie;
use App\Moteur\Exceptions\VariablesManquantes;
use App\Moteur\MiseAJourCopie;
use App\Notifications\AlerteAdmin;
use App\Notifications\MiseAJourTerminee;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mise à jour d'une copie sur le moteur. Trois issues : « reussie » (nouvelle version en ligne),
 * « retablie » (la nouvelle version a été refusée ou n'a pas démarré : la copie tourne sur la
 * précédente, avec ses données), « echouee » (le retour arrière lui-même a échoué : l'équipe est
 * prévenue). Le client est prévenu dans tous les cas.
 */
class MettreAJourCopie implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $miseAJourId)
    {
        $this->onQueue('moteur');
    }

    public function uniqueId(): string
    {
        return (string) $this->miseAJourId;
    }

    public function handle(MiseAJourCopie $service): void
    {
        $mise = MiseAJour::query()->with(['deploiement.appli', 'versVersion', 'deVersion'])->find($this->miseAJourId);
        $copie = $mise?->deploiement;
        if ($mise === null || $copie === null || $mise->statut !== 'en_file' || $copie->etat !== 'mise_a_jour' || $copie->appli === null) {
            return;
        }

        $mise->forceFill(['statut' => 'en_cours'])->save();
        $variables = [...(is_array($copie->variables) ? $copie->variables : []), ...($mise->variables ?? [])];
        $url = (string) $copie->url;

        try {
            $sauvegarde = $service->appliquer('d'.$copie->id, (string) $copie->repertoire, (string) $copie->appli->depot_url, (string) $mise->versVersion?->commit, $variables, (string) $copie->taille);

            $copie->forceFill(['version_id' => $mise->vers_version_id, 'variables' => $variables])->save();
            $mise->forceFill(['statut' => 'reussie', 'sauvegarde' => $sauvegarde, 'termine_le' => now()])->save();
            $copie->changerEtat('en_ligne', url: $url);
        } catch (MiseAJourRetablie $retablie) {
            $mise->forceFill(['statut' => 'retablie', 'erreur' => $retablie->getMessage(), 'sauvegarde' => $retablie->sauvegarde, 'termine_le' => now()])->save();
            $copie->changerEtat('en_ligne', url: $url);
        } catch (DepotInvalide|ManifestInvalide|VariablesManquantes|LancementEchoue $refus) {
            // Refusée avant tout arrêt : la copie n'a pas bougé.
            $mise->forceFill(['statut' => 'retablie', 'erreur' => 'Rien n’a été modifié : '.$refus->getMessage(), 'termine_le' => now()])->save();
            $copie->changerEtat('en_ligne', url: $url);
        } catch (Throwable $erreur) {
            report($erreur);
            Log::error('mise-a-jour: retour arrière impossible', ['copie' => $copie->id, 'erreur' => class_basename($erreur)]);
            $mise->forceFill(['statut' => 'echouee', 'erreur' => 'La copie n’a pas pu repartir.', 'termine_le' => now()])->save();
            $copie->changerEtat('echec', erreur: 'La mise à jour a échoué et la copie n’a pas pu repartir : notre équipe est prévenue.');
            AlerteAdmin::envoyer('Mise à jour d’une copie : retour arrière impossible', [
                "Copie n° {$copie->id} ({$copie->appli->nom}), version {$mise->deVersion?->numero} vers {$mise->versVersion?->numero}.",
                'Sauvegarde des volumes prise avant la mise à jour : storage/app/private/sauvegardes/d'.$copie->id.'.',
            ], '/admin');
        }

        $copie->utilisateur?->notify(new MiseAJourTerminee($mise->refresh()));
    }
}
