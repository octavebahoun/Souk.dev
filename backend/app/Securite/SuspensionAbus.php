<?php

namespace App\Securite;

use App\Jobs\SuspendreCopieAbus;
use App\Models\Deploiement;
use App\Notifications\CopieSuspendueAbus;
use Illuminate\Support\Facades\DB;

/**
 * Suspend une copie pour abus : par l'équipe (/admin/moderation) ou par la politique d'abus
 * (minage présumé, hameçonnage signalé). Conteneurs arrêtés, volumes gardés, client prévenu par
 * e-mail avec le motif ; seule l'équipe lève la sanction.
 */
final class SuspensionAbus
{
    public const SUSPENDUE = 'suspendue';

    public const DEJA_SUSPENDUE = 'deja_suspendue';

    public const PAS_EN_LIGNE = 'pas_en_ligne';

    /**
     * @return self::SUSPENDUE|self::DEJA_SUSPENDUE|self::PAS_EN_LIGNE
     */
    public function suspendre(Deploiement $deploiement, string $motif): string
    {
        // Le verrou ne tient que dans une transaction : deux suspensions concurrentes
        // ne passent pas toutes deux le contrôle. Travail et e-mail après validation.
        $resultat = DB::transaction(function () use ($deploiement, $motif): string {
            $verrouille = Deploiement::query()->lockForUpdate()->findOrFail($deploiement->id);

            if ($verrouille->suspension_admin_motif !== null) {
                return self::DEJA_SUSPENDUE;
            }

            if ($verrouille->etat !== 'en_ligne') {
                return self::PAS_EN_LIGNE;
            }

            $verrouille->forceFill(['suspension_admin_motif' => $motif])->save();

            return self::SUSPENDUE;
        });

        if ($resultat !== self::SUSPENDUE) {
            return $resultat;
        }

        $deploiement->refresh()->changerEtat('suspendu');
        SuspendreCopieAbus::dispatch($deploiement->id);

        $deploiement->loadMissing('utilisateur');
        $deploiement->utilisateur?->notify(new CopieSuspendueAbus($deploiement, $motif));

        return self::SUSPENDUE;
    }
}
