<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Profil;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Connexion à un compte du jeu de démo, sans GitHub ni e-mail : pour le développement, les tests
 * de bout en bout et le jury du concours (DEMO_JURY). Introuvable (404) sinon.
 */
final class DemoController extends Controller
{
    public function connecter(Request $request): Response
    {
        // En production, seulement en mode jury (la configuration refuse DEMO_CONNEXION seul).
        abort_unless((bool) config('app.demo_connexion') && (! app()->isProduction() || (bool) config('app.demo_jury')), 404);

        $donnees = $request->validate([
            'compte' => ['required', 'string', 'max:64'],
        ]);

        $compte = User::query()
            ->where('est_demo', true)
            ->where('username', $donnees['compte'])
            ->first();

        abort_if($compte === null, 404);

        // Le compte admin de démo ne s'ouvre que sur un poste de développement, jamais sur une
        // préproduction joignable de l'extérieur (même avec DEMO_CONNEXION=true).
        abort_if($compte->est_admin && ! app()->environment('local', 'testing'), 404);

        abort_if($compte->estSuspendu(), 403, 'Compte suspendu.');

        Auth::login($compte);
        $request->session()->regenerate();

        return response()->noContent();
    }

    /**
     * Les comptes de démo qu'on peut ouvrir depuis le site (jamais l'admin hors développement) :
     * la page de connexion les affiche au jury. 404 quand la connexion de démo est fermée.
     */
    public function comptes(): JsonResponse
    {
        abort_unless((bool) config('app.demo_connexion') && (! app()->isProduction() || (bool) config('app.demo_jury')), 404);

        $comptes = User::query()
            ->where('est_demo', true)
            ->whereNull('suspendu_le')
            ->when(! app()->environment('local', 'testing'), fn ($requete) => $requete->where('est_admin', false))
            ->orderByRaw("case when profil = 'client' then 1 else 0 end")
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $comptes->map(function (User $compte): array {
                $profil = $compte->profil;

                return [
                    'username' => (string) $compte->username,
                    'nom' => $compte->name,
                    'profil' => $profil instanceof Profil ? $profil->value : (string) $profil,
                    'est_admin' => (bool) $compte->est_admin,
                    'pays' => $compte->pays,
                    'avatar_url' => $compte->avatar_url,
                    'bio' => $compte->bio === null ? null : Str::limit($compte->bio, 140),
                ];
            })->values(),
            'paiement_simule' => config('facturation.passerelle') === 'simulee',
        ]);
    }
}
