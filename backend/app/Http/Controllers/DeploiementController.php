<?php

namespace App\Http\Controllers;

use App\Models\Appli;
use App\Models\Deploiement;
use App\Moteur\LimitesTaille;
use App\Paiement\Facturation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeploiementController extends Controller
{
    /**
     * Commande d'une copie : abonnement et première facture. La copie attend le paiement
     * (attente_paiement) et ne démarre que lorsque SasPay le confirme.
     */
    public function store(Request $request, Appli $appli, Facturation $facturation): JsonResponse
    {
        $variables = $this->variables($request, $appli);
        $this->taille($appli);

        $deploiement = $facturation->commander($appli, $request->user(), $variables);
        $deploiement->diffuser();

        return response()->json($deploiement->refresh()->versApi(), 202);
    }

    public function index(Request $request): JsonResource
    {
        $request->validate([
            'type' => ['sometimes', 'in:client,test,correctif'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $deploiements = Deploiement::query()
            ->where('user_id', $request->user()->id)
            ->with(['appli', 'abonnement.facturesAPayer'])
            ->when($request->filled('type'), fn ($requete) => $requete->where('type', $request->string('type')))
            ->latest()
            ->paginate($request->integer('per_page', 20))
            ->through(fn (Deploiement $deploiement) => $deploiement->versApi());

        return JsonResource::collection($deploiements);
    }

    public function show(Deploiement $deploiement): JsonResponse
    {
        Gate::authorize('view', $deploiement);

        return response()->json($deploiement->versApi());
    }

    /**
     * L'arrêt tourne sur le moteur : arret_en_cours tout de suite, arrete par Reverb ensuite.
     * Une copie client arrêtée par son client n'est plus facturée.
     */
    public function destroy(Deploiement $deploiement, Facturation $facturation): JsonResponse
    {
        Gate::authorize('delete', $deploiement);

        if ($deploiement->abonnement !== null) {
            $facturation->resilier($deploiement->abonnement);
        }

        $deploiement->demanderArret();

        return response()->json($deploiement->refresh()->versApi(), 202);
    }

    /**
     * Les variables du client, vérifiées avant de payer : une variable obligatoire manquante
     * ferait échouer le déploiement après le paiement.
     *
     * @return array<string, string>
     */
    private function variables(Request $request, Appli $appli): array
    {
        $donnees = $request->validate([
            'variables' => ['present', 'array'],
            'variables.*' => ['nullable', 'string', 'max:500'],
        ]);

        $variables = $donnees['variables'];

        if (array_is_list($variables) && $variables !== []) {
            throw ValidationException::withMessages([
                'variables' => 'Les variables doivent être un objet nom → valeur.',
            ]);
        }

        $attendues = collect($appli->variables_client ?? [])->keyBy('nom');
        $erreurs = [];

        foreach (array_keys($variables) as $nom) {
            if (! $attendues->has($nom)) {
                $erreurs["variables.{$nom}"] = "La variable {$nom} n'est pas demandée par cette appli.";
            }
        }

        foreach ($attendues as $nom => $variable) {
            if (($variable['requis'] ?? false) && trim((string) ($variables[$nom] ?? '')) === '') {
                $erreurs["variables.{$nom}"] = "Renseignez « {$variable['libelle']} ».";
            }
        }

        if ($erreurs !== []) {
            throw ValidationException::withMessages($erreurs);
        }

        return array_map(fn ($valeur) => trim((string) $valeur), $variables);
    }

    private function taille(Appli $appli): string
    {
        $taille = (string) $appli->taille;

        if (! LimitesTaille::connue($taille)) {
            throw ValidationException::withMessages([
                'taille' => 'La taille doit être petite, moyenne ou grande.',
            ]);
        }

        return $taille;
    }
}
