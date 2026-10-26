<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CoordonneesReversement;
use App\Notifications\ConfirmerCoordonneesReversement;
use App\Notifications\Courriel;
use App\Paiement\NumeroMobileMoney;
use App\Paiement\Reversements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Numéro Mobile Money de reversement : saisi deux fois, chiffré, masqué, et inutilisable tant
 * que le lien envoyé par e-mail n'est pas ouvert (un vol de session ne détourne pas l'argent).
 */
final class CoordonneesReversementController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(CoordonneesReversement::query()->where('user_id', $request->user()->id)->first()?->versApi());
    }

    public function update(Request $request, Reversements $reversements): JsonResponse
    {
        if ($reversements->enCours($request->user()) !== null) {
            throw new ConflictHttpException('Un reversement est en cours : changez de numéro une fois qu’il est terminé.');
        }

        $reseaux = collect(config('facturation.reseaux'));
        $donnees = $request->validate([
            'pays' => ['required', 'string', Rule::in($reseaux->pluck('pays')->unique()->all())],
            'reseau' => ['required', 'string', Rule::in($reseaux->pluck('code')->all())],
            'numero' => ['required', 'string', 'max:20'],
            'numero_confirmation' => ['required', 'string', 'max:20', 'same:numero'],
        ], [
            'numero_confirmation.same' => 'Les deux numéros ne correspondent pas.',
            'pays.in' => 'Ce pays n’est pas encore pris en charge pour les reversements.',
        ]);

        if ($reseaux->firstWhere('code', $donnees['reseau'])['pays'] !== $donnees['pays']) {
            throw ValidationException::withMessages(['reseau' => 'Ce réseau n’existe pas dans le pays choisi.']);
        }

        try {
            $numero = NumeroMobileMoney::normaliser($donnees['pays'], $donnees['numero']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['numero' => $exception->getMessage()]);
        }

        $jeton = Str::random(48);
        $coordonnees = CoordonneesReversement::query()->updateOrCreate(['user_id' => $request->user()->id], [
            'pays' => $donnees['pays'],
            'reseau' => $donnees['reseau'],
            'numero' => $numero,
            'numero_masque' => NumeroMobileMoney::masquer($donnees['pays'], $numero),
        ]);
        $coordonnees->forceFill([
            'confirme_le' => null,
            'jeton_confirmation_hash' => hash('sha256', $jeton),
            'jeton_expire_le' => now()->addDay(),
        ])->save();

        $request->user()->notify(new ConfirmerCoordonneesReversement($coordonnees, $jeton));

        return response()->json($coordonnees->versApi());
    }

    /**
     * Le lien de l'e-mail ouvre une page avec un bouton : les analyseurs de liens des
     * messageries ouvrent les liens tout seuls, ils ne doivent rien pouvoir confirmer.
     */
    public function afficher(string $jeton): View|RedirectResponse
    {
        $coordonnees = $this->parJeton($jeton);

        if ($coordonnees === null) {
            return redirect()->away(Courriel::front('/revenus?confirmation=expiree'));
        }

        return view('plateforme.confirmer-reversement', [
            'coordonnees' => $coordonnees,
            'jeton' => $jeton,
            'reseau' => collect(config('facturation.reseaux'))->firstWhere('code', $coordonnees->reseau)['nom'] ?? $coordonnees->reseau,
            'accueil' => Courriel::front('/'),
        ]);
    }

    public function confirmer(string $jeton): RedirectResponse
    {
        $coordonnees = $this->parJeton($jeton);

        if ($coordonnees === null) {
            return redirect()->away(Courriel::front('/revenus?confirmation=expiree'));
        }

        $coordonnees->forceFill(['confirme_le' => now(), 'jeton_confirmation_hash' => null, 'jeton_expire_le' => null])->save();

        return redirect()->away(Courriel::front('/revenus?confirmation=ok'));
    }

    private function parJeton(string $jeton): ?CoordonneesReversement
    {
        return CoordonneesReversement::query()
            ->where('jeton_confirmation_hash', hash('sha256', $jeton))
            ->where('jeton_expire_le', '>', now())
            ->first();
    }
}
