<?php

namespace App\Paiement;

use App\Jobs\InitierReversement;
use App\Models\CoordonneesReversement;
use App\Models\Reversement;
use App\Models\User;
use App\Notifications\AlerteAdmin;
use App\Notifications\ReversementEffectue;
use App\Paiement\Passerelles\DemandeReversement;
use App\Paiement\Passerelles\PasserelleIndisponible;
use App\Paiement\Passerelles\PasserelleRefus;
use App\Paiement\Passerelles\PasserelleReversement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Reversements aux devs : tout le solde, sur le Mobile Money confirmé, un seul à la fois.
 * Chaque mois automatiquement, ou à la demande à partir de 5 000 F. Les frais du payout sont
 * pris sur la marge de Souk.dev, jamais sur le solde du dev (ce serait une commission déguisée).
 */
final class Reversements
{
    public function __construct(
        private GrandLivre $grandLivre,
        private PasserelleReversement $passerelle,
    ) {}

    /** Pourquoi un reversement est impossible maintenant, ou null s'il est possible. */
    public function blocage(User $dev, string $declenchement = 'demande'): ?string
    {
        $coordonnees = CoordonneesReversement::query()->where('user_id', $dev->id)->first();
        $solde = $this->grandLivre->soldeDev($dev->id);
        $seuil = (int) config('facturation.seuil_reversement');

        // Espaces insécables : « 5 000 F » ne se coupe jamais en fin de ligne.
        $montant = fn (int $valeur): string => number_format($valeur, 0, ',', "\u{202F}")."\u{00A0}F";

        return match (true) {
            $this->passerelle->nom() === 'inactif' => 'Les reversements sur Mobile Money ouvrent bientôt : votre solde vous attend.',
            $coordonnees === null => 'Ajoutez votre numéro Mobile Money pour recevoir vos revenus.',
            ! $coordonnees->estConfirme() => 'Confirmez votre numéro Mobile Money avec le lien reçu par e-mail.',
            $this->enCours($dev) !== null => 'Un reversement est déjà en cours.',
            $solde <= 0 => 'Rien à recevoir pour l’instant.',
            $declenchement === 'demande' && $solde < $seuil => 'Le reversement à la demande commence à '.$montant($seuil).' ; le reste part le 1er du mois.',
            default => null,
        };
    }

    public function enCours(User $dev): ?Reversement
    {
        return Reversement::query()->where('dev_id', $dev->id)->where('statut', 'en_attente')->latest('id')->first();
    }

    /**
     * Réserve tout le solde (grand livre) puis lance le payout en arrière-plan, sous verrou :
     * deux demandes simultanées ne paient jamais deux fois.
     */
    public function demander(User $dev, string $declenchement): Reversement
    {
        $reversement = DB::transaction(function () use ($dev, $declenchement): Reversement {
            User::query()->whereKey($dev->id)->lockForUpdate()->first();

            if (($raison = $this->blocage($dev, $declenchement)) !== null) {
                if ($this->enCours($dev) !== null) {
                    throw new ConflictHttpException($raison);
                }

                throw ValidationException::withMessages(['reversement' => $raison]);
            }

            $coordonnees = CoordonneesReversement::query()->where('user_id', $dev->id)->firstOrFail();
            $reversement = Reversement::query()->create([
                'dev_id' => $dev->id,
                'montant' => $this->grandLivre->soldeDev($dev->id),
                'passerelle' => $this->passerelle->nom(),
                'cle_idempotence' => (string) Str::uuid(),
                'pays' => $coordonnees->pays,
                'reseau' => $coordonnees->reseau,
                'numero_masque' => $coordonnees->numero_masque,
                'beneficiaire' => [
                    'numero' => $coordonnees->numero,
                    'email' => (string) ($dev->email ?: config('mail.from.address')),
                    'nom' => (string) ($dev->name ?: $dev->username),
                ],
                'declenchement' => $declenchement,
                'statut' => 'en_attente',
            ]);

            $this->grandLivre->reserverReversement($reversement);

            return $reversement;
        });

        InitierReversement::dispatch($reversement->id)->afterCommit();

        return $reversement;
    }

    /**
     * Appel du payout avec la clé d'idempotence gardée en base et le bénéficiaire figé : une
     * reprise envoie exactement la même requête et SasPay renvoie la réponse d'origine.
     *
     * @throws PasserelleIndisponible quand il faut réessayer plus tard (le job s'en charge)
     */
    public function initier(Reversement $reversement): void
    {
        if ($reversement->statut !== 'en_attente' || $reversement->payout_id !== null) {
            return;
        }

        $beneficiaire = $reversement->beneficiaire;
        $reversement->forceFill(['essais' => $reversement->essais + 1])->save();

        try {
            $payout = $this->passerelle->initier(new DemandeReversement(
                montant: $reversement->montant,
                pays: $reversement->pays,
                reseau: $reversement->reseau,
                numero: (string) $beneficiaire['numero'],
                email: (string) $beneficiaire['email'],
                nom: (string) $beneficiaire['nom'],
                description: "Souk.dev — reversement n° {$reversement->id}",
                metadata: ['reversement_id' => $reversement->id],
            ), $reversement->cle_idempotence);
        } catch (PasserelleRefus $exception) {
            // 409 : la première tentative tourne encore chez SasPay (ou a abouti). Rendre le solde
            // maintenant paierait deux fois : on réessaie plus tard avec la même clé.
            if ($exception->statutHttp === 409) {
                Log::warning('reversement: conflit d\'idempotence, nouvel essai plus tard', ['reversement' => $reversement->id, 'code' => $exception->codeErreur]);

                throw new PasserelleIndisponible('Reversement en cours de traitement chez SasPay.');
            }

            Log::error('reversement: refusé', ['reversement' => $reversement->id, 'statut' => $exception->statutHttp, 'code' => $exception->codeErreur]);

            // Une reprise refusée (clé ou IP changée entre deux essais) ne prouve pas que le premier
            // appel n'a rien créé : le solde reste réservé, l'équipe vérifie chez SasPay.
            if ($reversement->essais > 1) {
                $this->aVerifier($reversement, "Refus de SasPay ({$exception->statutHttp}) après une tentative interrompue.");

                return;
            }

            // 401, 403, 422 au premier appel : SasPay n'a rien créé ; le dev retrouve son solde.
            $this->echouer($reversement, 'Le prestataire a refusé le reversement.');

            return;
        }

        $reversement->forceFill(['payout_id' => $payout])->save();
        $this->verifier($reversement);
    }

    /** Vérification du payout : succès (frais sur la marge) ou échec (solde rendu au dev). */
    public function verifier(Reversement $reversement): void
    {
        if ($reversement->statut !== 'en_attente' || $reversement->payout_id === null) {
            return;
        }

        try {
            $verification = $this->passerelle->verifier($reversement->payout_id);
        } catch (PasserelleIndisponible|PasserelleRefus $exception) {
            Log::warning('reversement: vérification impossible', ['reversement' => $reversement->id, 'erreur' => $exception->getMessage()]);

            return;
        }

        if ($verification->statut === 'SUCCESS') {
            DB::transaction(function () use ($reversement, $verification): void {
                $verrouille = Reversement::query()->lockForUpdate()->findOrFail($reversement->id);

                if ($verrouille->statut !== 'en_attente') {
                    return;
                }

                $verrouille->forceFill(['statut' => 'reussi', 'frais' => $verification->frais, 'verifie_le' => now()])->save();
                $this->grandLivre->fraisReversement($verrouille, (int) $verification->frais);
            });
            $reversement->refresh()->dev?->notify(new ReversementEffectue($reversement));

            return;
        }

        if (in_array($verification->statut, ['FAILED', 'CANCELLED'], true)) {
            $this->echouer($reversement, $verification->erreur ?? 'Le réseau a refusé le reversement.');

            return;
        }

        $reversement->forceFill(['verifie_le' => now()])->save();
    }

    /** À trancher par l'équipe : le solde reste réservé, une alerte part une seule fois. */
    public function aVerifier(Reversement $reversement, string $raison): void
    {
        if ($reversement->alerte_le !== null) {
            return;
        }

        $reversement->forceFill(['erreur' => 'À vérifier par l’équipe.', 'alerte_le' => now()])->save();
        AlerteAdmin::envoyer("Reversement n° {$reversement->id} à vérifier", [
            "{$raison} Montant : {$reversement->montant} F, vers {$reversement->numero_masque} ({$reversement->reseau}).",
            'Cherchez le payout dans le tableau de bord SasPay (clé d’idempotence '.$reversement->cle_idempotence.') avant de rendre le solde au dev.',
        ]);
    }

    private function echouer(Reversement $reversement, string $erreur): void
    {
        DB::transaction(function () use ($reversement, $erreur): void {
            $verrouille = Reversement::query()->lockForUpdate()->findOrFail($reversement->id);

            if ($verrouille->statut !== 'en_attente') {
                return;
            }

            $verrouille->forceFill(['statut' => 'echoue', 'erreur' => $erreur, 'verifie_le' => now()])->save();
            $this->grandLivre->annulerReversement($verrouille);
        });

        $reversement->refresh()->dev?->notify(new ReversementEffectue($reversement));
    }
}
