<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Comptes\EmetteurLienMagique;
use App\Comptes\FrontUrl;
use App\Enums\Profil;
use App\Http\Requests\DemanderLienMagiqueRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function utiliser(string $token, EmetteurLienMagique $emetteur, FrontUrl $front): JsonResponse|RedirectResponse
    {
        $compte = $emetteur->utiliser($token);

        if ($compte === null) {
            return response()->json([
                'message' => 'Lien expiré ou déjà utilisé.',
            ], 410);
        }

        Auth::login($compte);
        request()->session()->regenerate();

        $profil = $compte->profil instanceof Profil ? $compte->profil : Profil::Client;

        return redirect()->away($front->apresConnexion($profil));
    }
}
