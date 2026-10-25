<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Comptes\FrontUrl;
use App\Comptes\GithubConnexion;
use App\Enums\Profil;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as UtilisateurGithub;
use Throwable;

final class GithubController extends Controller
{
    public function redirect(Request $request, FrontUrl $front): JsonResponse|RedirectResponse
    {
        $profil = $request->string('profil')->toString();

        if ($profil === '') {
            $profil = Profil::Dev->value;
        }

        if (Profil::tryFrom($profil) === null) {
            return response()->json([
                'message' => 'Le choix d\'entrée doit être dev ou client.',
                'errors' => [
                    'profil' => ['Le choix d\'entrée doit être dev ou client.'],
                ],
            ], 422);
        }

        if (! Auth::check()) {
            $request->session()->put('profil_entree', $profil);
        }

        if (! config('services.github.client_id') || ! config('services.github.client_secret')) {
            return redirect()->away($front->erreur('github_non_configure'));
        }

        return Socialite::driver('github')
            ->scopes(['read:user', 'user:email'])
            ->redirect();
    }

    public function callback(Request $request, GithubConnexion $connexion, FrontUrl $front): RedirectResponse
    {
        try {
            $github = Socialite::driver('github')->user();
        } catch (InvalidStateException $exception) {
            // Session perdue entre l'aller et le retour (souvent localhost d'un côté, 127.0.0.1 de l'autre).
            report($exception);

            return redirect()->away($front->erreur('github'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->away($front->erreur('github'));
        }

        if (! $github instanceof UtilisateurGithub) {
            return redirect()->away($front->erreur('github'));
        }

        $profil = Profil::tryFrom((string) $request->session()->pull('profil_entree', Profil::Dev->value)) ?? Profil::Dev;
        /** @var User|null $actuel */
        $actuel = $request->user();
        $resultat = $connexion->relier($actuel, $github, $profil);

        if (! $resultat->reussi() || $resultat->compte === null) {
            return redirect()->away($front->erreur($resultat->erreur ?? 'github'));
        }

        Auth::login($resultat->compte);
        $request->session()->regenerate();

        $profilCompte = $resultat->compte->profil instanceof Profil
            ? $resultat->compte->profil
            : Profil::Dev;

        return redirect()->away($front->apresConnexion($profilCompte));
    }
}
