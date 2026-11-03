<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SimulationSasPay;
use App\Paiement\Passerelles\SimulateurSasPay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page de paiement du simulateur (développement et tests) : elle imite le checkout SasPay.
 * Introuvable (404) avec la vraie passerelle et toujours en production.
 */
final class SimulateurSasPayController extends Controller
{
    public function afficher(string $session): View
    {
        $simulation = $this->simulation($session);

        return view('simulateur-saspay', [
            'simulation' => $simulation,
            'issue' => null,
            'reseaux' => collect(config('facturation.reseaux'))->where('pays', 'BJ')->values()->all(),
        ]);
    }

    public function choisir(Request $request, string $session, SimulateurSasPay $simulateur): RedirectResponse|View
    {
        $simulation = $this->simulation($session);
        $donnees = $request->validate([
            'issue' => ['required', 'in:succes,echec,attente'],
            'reseau' => ['nullable', 'string', 'max:32'],
        ]);

        $simulateur->choisir($simulation, $donnees['issue'], $donnees['reseau'] ?? null);

        // Comme SasPay : retour automatique vers le site seulement après un succès.
        if ($donnees['issue'] === 'succes' && is_string($simulation->return_url) && $simulation->return_url !== '') {
            return redirect()->away($simulation->return_url);
        }

        return view('simulateur-saspay', [
            'simulation' => $simulation->refresh(),
            'issue' => $donnees['issue'],
            'reseaux' => collect(config('facturation.reseaux'))->where('pays', 'BJ')->values()->all(),
        ]);
    }

    private function simulation(string $session): SimulationSasPay
    {
        abort_unless(config('facturation.passerelle') === 'simulee' && ! app()->isProduction(), 404);
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $session) === 1, 404);

        return SimulationSasPay::query()->where('type', 'session')->findOrFail($session);
    }
}
