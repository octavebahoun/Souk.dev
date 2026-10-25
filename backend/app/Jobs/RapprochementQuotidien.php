<?php

namespace App\Jobs;

use App\Models\Paiement;
use App\Models\Rapprochement;
use App\Notifications\EcartRapprochement;
use App\Paiement\Encaissement;
use App\Paiement\Passerelles\PasserelleIndisponible;
use App\Paiement\Passerelles\PasserellePaiement;
use App\Paiement\Passerelles\PasserelleRefus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Chaque nuit : les transactions réussies de la veille chez SasPay (GET /transactions/) contre
 * les paiements enregistrés. Un paiement réussi resté « en attente » ici (webhook perdu) est
 * corrigé par une revérification ; un écart qui demande un humain prévient l'admin.
 */
class RapprochementQuotidien implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [300, 1800];

    public function __construct(public ?string $jour = null)
    {
        $this->onQueue('paiements');
    }

    public function handle(PasserellePaiement $passerelle, Encaissement $encaissement): void
    {
        $jour = $this->jour !== null ? CarbonImmutable::parse($this->jour) : CarbonImmutable::yesterday();
        $debut = $jour->startOfDay();
        $fin = $jour->endOfDay();
        $ecarts = [];
        $distantes = 0;
        $vus = [];

        // D'abord les sessions encore ouvertes de la journée : leur vérification relie les
        // transactions (webhook perdu, client parti avant le retour).
        Paiement::query()
            ->whereNotNull('session_id')
            ->whereIn('statut', ['en_attente', 'echoue'])
            ->whereBetween('created_at', [$debut->subDay(), $fin])
            ->orderBy('id')
            ->each(function (Paiement $paiement) use ($encaissement, &$ecarts): void {
                $avant = $paiement->statut;

                try {
                    $paiement = $encaissement->verifier($paiement);
                } catch (PasserelleIndisponible|PasserelleRefus) {
                    return;
                }

                if ($paiement->statut === 'reussi') {
                    $ecarts[] = ['type' => 'corrige', 'paiement_id' => $paiement->id, 'avant' => $avant, 'apres' => 'reussi'];
                }
            });

        foreach ($passerelle->transactionsReussies($debut, $fin) as $transaction) {
            $paiement = Paiement::query()->where('transaction_id', $transaction->id)->first();

            if ($paiement === null) {
                // Le compte marchand peut encaisser pour d'autres activités : seules les nôtres comptent.
                if (! str_starts_with((string) $transaction->description, 'Souk.dev')) {
                    continue;
                }

                $distantes++;
                $ecarts[] = ['type' => 'inconnue_localement', 'transaction_id' => $transaction->id, 'reference' => $transaction->reference, 'montant' => $transaction->montantDemande];

                continue;
            }

            $distantes++;
            $vus[] = $paiement->id;

            if ($transaction->montantDemande !== $paiement->montant) {
                $ecarts[] = ['type' => 'montant_different', 'paiement_id' => $paiement->id, 'montant_local' => $paiement->montant, 'montant_saspay' => $transaction->montantDemande];
            }

            if ($paiement->statut !== 'reussi') {
                $avant = $paiement->statut;
                $paiement = $encaissement->verifier($paiement);
                $ecarts[] = ['type' => 'corrige', 'paiement_id' => $paiement->id, 'avant' => $avant, 'apres' => $paiement->statut];
            }
        }

        // Réussi chez nous, absent de la liste de SasPay : on revérifie un par un (la transaction
        // peut appartenir à la veille ou au lendemain de la fenêtre).
        Paiement::query()
            ->where('statut', 'reussi')
            ->whereNotNull('transaction_id')
            ->whereBetween('verifie_le', [$debut, $fin])
            ->whereNotIn('id', $vus)
            ->orderBy('id')
            ->each(function (Paiement $paiement) use ($passerelle, &$ecarts): void {
                try {
                    $verification = $passerelle->verifierPaiement((string) $paiement->transaction_id);
                } catch (PasserelleIndisponible|PasserelleRefus $exception) {
                    $ecarts[] = ['type' => 'verification_impossible', 'paiement_id' => $paiement->id, 'erreur' => $exception->getMessage()];

                    return;
                }

                if (! $verification->reussi()) {
                    $ecarts[] = ['type' => 'absente_chez_saspay', 'paiement_id' => $paiement->id, 'statut_saspay' => $verification->statut];
                }
            });

        $locaux = Paiement::query()->where('statut', 'reussi')->whereBetween('verifie_le', [$debut, $fin])->count();

        $rapprochement = Rapprochement::query()->updateOrCreate(['jour' => $jour->toDateString()], [
            'transactions_saspay' => $distantes,
            'paiements_locaux' => $locaux,
            'ecarts' => $ecarts,
        ]);

        $aTraiter = array_values(array_filter($ecarts, fn (array $ecart) => $ecart['type'] !== 'corrige'));

        if ($aTraiter !== []) {
            Notification::route('mail', (string) config('app.admin_email'))->notify(new EcartRapprochement($rapprochement, count($aTraiter)));
        }
    }
}
