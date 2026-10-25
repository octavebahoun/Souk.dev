<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Connexion à un compte du jeu de démo, sans GitHub ni e-mail : pour le développement
 * et les tests de bout en bout. Introuvable (404) hors de DEMO_CONNEXION et en production.
 */
final class DemoController extends Controller
{
    public function connecter(Request $request): Response
    {
        // Double garde : la configuration, et jamais en production quoi qu'elle dise.
        abort_unless((bool) config('app.demo_connexion') && ! app()->isProduction(), 404);

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

        Auth::login($compte);
        $request->session()->regenerate();

        return response()->noContent();
    }
}
