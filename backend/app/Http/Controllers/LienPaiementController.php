<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Facture;
use App\Notifications\Courriel;
use App\Paiement\Encaissement;
use App\Paiement\Passerelles\PasserelleIndisponible;
use App\Paiement\Passerelles\PasserelleRefus;
use Illuminate\Http\RedirectResponse;

/**
 * Lien de paiement signé, envoyé par e-mail : payer sans se connecter. La signature autorise
 * seulement à payer cette facture (au pire, quelqu'un paie la facture d'un autre).
 */
final class LienPaiementController extends Controller
{
    public function ouvrir(int $id, Encaissement $encaissement): RedirectResponse
    {
        $facture = Facture::query()->with('client')->findOrFail($id);

        if ($facture->statut !== 'a_payer') {
            return redirect()->away(Courriel::front('/paiements/'.$facture->id));
        }

        try {
            $paiement = $encaissement->ouvrirSession($facture, $facture->client);
        } catch (PasserelleIndisponible|PasserelleRefus) {
            return redirect()->away(Courriel::front('/paiements/'.$facture->id.'?indisponible=1'));
        }

        return redirect()->away((string) $paiement->checkout_url);
    }
}
