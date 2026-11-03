<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Comptes\EmetteurLienMagique;
use App\Comptes\FrontUrl;
use App\Enums\Profil;
use App\Http\Requests\DemanderLienMagiqueRequest;
use App\Notifications\Courriel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class LienMagiqueController extends Controller
{
    public function demander(DemanderLienMagiqueRequest $request, EmetteurLienMagique $emetteur): JsonResponse
    {
        $emetteur->demander($request->string('email')->toString());

        return response()->json([
            'message' => 'Si cette adresse est valide, un email a été envoyé.',
        ], 202);
    }

    /**
     * Page ouverte depuis l'e-mail : elle demande un clic avant d'ouvrir la session. Les analyseurs
     * de messagerie qui ouvrent les liens ne consomment donc plus le lien, et la personne voit quel
     * compte elle s'apprête à ouvrir (un lien envoyé par quelqu'un d'autre se repère).
     */
    public function afficher(Request $request, string $token, EmetteurLienMagique $emetteur): JsonResponse|Response
    {
        $adresse = $emetteur->adresseSiValable($token);

        if ($adresse === null) {
            return $this->refus($request, 'expire', 410);
        }

        return response()->view('plateforme.lien-magique', [
            'etat' => 'confirmer',
            'adresse' => $adresse,
            'action' => url('/auth/lien-magique/'.$token),
            'connexion' => Courriel::front('/connexion'),
            'accueil' => Courriel::front('/'),
        ]);
    }

    public function utiliser(Request $request, string $token, EmetteurLienMagique $emetteur, FrontUrl $front): JsonResponse|RedirectResponse|Response
    {
        $compte = $emetteur->utiliser($token);

        if ($compte === null) {
            return $this->refus($request, 'expire', 410);
        }

        if ($compte->estSuspendu()) {
            return $this->refus($request, 'suspendu', 403, (string) $compte->suspension_motif);
        }

        Auth::login($compte);
        request()->session()->regenerate();

        $profil = $compte->profil instanceof Profil ? $compte->profil : Profil::Client;

        return redirect()->away($front->apresConnexion($profil));
    }

    private function refus(Request $request, string $etat, int $statut, string $motif = ''): JsonResponse|Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $etat === 'suspendu' ? 'Compte suspendu : '.$motif : 'Lien expiré ou déjà utilisé.',
            ], $statut);
        }

        return response()->view('plateforme.lien-magique', [
            'etat' => $etat,
            'motif' => $motif,
            'connexion' => Courriel::front('/connexion'),
            'accueil' => Courriel::front('/'),
        ], $statut);
    }
}
