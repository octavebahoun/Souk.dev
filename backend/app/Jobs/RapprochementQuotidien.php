<?php

namespace App\Jobs;

use App\Models\Paiement;
use App\Models\Rapprochement;
use App\Notifications\AlerteAdmin;
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
            ->where(fn ($requete) => $requete
                ->whereIn('statut', ['en_attente', 'echoue'])
                ->orWhere(fn ($annule) => $annule->where('statut', 'annule')->whereNotNull('transaction_id')))
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
                    $ecarts[] = $this->ecart('corrige', $paiement->transaction_id ?? "paiement n° {$paiement->id}", "Paiement {$avant} ici, réussi chez SasPay : rattrapé par la vérification.");
                }
            });

        try {
            $transactions = iterator_to_array($passerelle->transactionsReussies($debut, $fin), false);
        } catch (PasserelleIndisponible|PasserelleRefus $exception) {
            $transactions = [];
            $ecarts[] = $this->ecart('verification_impossible', 'GET /transactions/', "La liste des transactions de SasPay n'a pas pu être lue ({$exception->getMessage()}).");
        }

        foreach ($transactions as $transaction) {
            $paiement = Paiement::query()->where('transaction_id', $transaction->id)->first();

            if ($paiement === null) {
                // Le compte marchand peut encaisser pour d'autres activités : seules les nôtres comptent.
                if (! str_starts_with((string) $transaction->description, 'Souk.dev')) {
                    continue;
                }

                $distantes++;
                $ecarts[] = $this->ecart('inconnue_localement', $transaction->id, "Transaction réussie de {$transaction->montantDemande} F chez SasPay (référence {$transaction->reference}), reliée à aucun paiement ici.");

                continue;
            }

            $distantes++;
            $vus[] = $paiement->id;

            if ($transaction->montantDemande !== $paiement->montant) {
                $ecarts[] = $this->ecart('montant_different', $transaction->id, "Paiement n° {$paiement->id} : {$paiement->montant} F attendus, {$transaction->montantDemande} F chez SasPay.");
            }

            if ($paiement->statut !== 'reussi') {
                $avant = $paiement->statut;

                try {
                    $paiement = $encaissement->verifier($paiement);
                } catch (PasserelleIndisponible|PasserelleRefus $exception) {
                    $ecarts[] = $this->ecart('verification_impossible', $transaction->id, "Paiement n° {$paiement->id} réussi chez SasPay, {$avant} ici ; vérification impossible ({$exception->getMessage()}).");

                    continue;
                }

                // « corrigé » seulement si le paiement est bien crédité ; sinon un humain regarde.
                $ecarts[] = $paiement->statut === 'reussi'
                    ? $this->ecart('corrige', $transaction->id, "Paiement n° {$paiement->id} {$avant} ici, réussi après vérification.")
                    : $this->ecart('verification_impossible', $transaction->id, "Paiement n° {$paiement->id} réussi chez SasPay, toujours {$paiement->statut} ici après vérification.");
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
                    $ecarts[] = $this->ecart('verification_impossible', (string) $paiement->transaction_id, "Paiement n° {$paiement->id} : SasPay n'a pas répondu ({$exception->getMessage()}).");

                    return;
                }

                if (! $verification->reussi()) {
                    $ecarts[] = $this->ecart('absente_chez_saspay', (string) $paiement->transaction_id, "Paiement n° {$paiement->id} réussi ici, {$verification->statut} chez SasPay.");
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

    /** Le rapprochement n'a pas pu tourner : l'équipe le sait. */
    public function failed(\Throwable $exception): void
    {
        AlerteAdmin::envoyer('Rapprochement SasPay impossible', [
            'Le rapprochement quotidien a échoué : '.$exception->getMessage(),
            'Les paiements restent revérifiés toutes les 15 minutes ; relancez-le après avoir vérifié SasPay.',
        ]);
    }

    /**
     * Un écart au format du contrat (openapi.yaml, Rapprochement).
     *
     * @return array{type: string, reference: string, detail: string}
     */
    private function ecart(string $type, string $reference, string $detail): array
    {
        return ['type' => $type, 'reference' => $reference, 'detail' => $detail];
    }
}
