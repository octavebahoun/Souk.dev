<?php

namespace App\Http\Controllers;

use App\Comptes\ProfilCalcule;
use App\Enums\Profil;
use App\Http\Resources\MissionResource;
use App\Models\Appli;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MissionController extends Controller
{
    // GET /missions : mes missions, comme client ou comme auteur
    public function index(Request $request)
    {
        $user = $request->user();
        $parPage = min(max((int) $request->query('per_page', 20), 1), 50);

        $missions = Mission::with(['client', 'auteur'])
            ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('auteur_id', $user->id))
            ->latest()
            ->paginate($parPage);

        return MissionResource::collection($missions);
    }

    // GET /missions/{id} : réservée au client et à l'auteur
    public function show(Request $request, Mission $mission)
    {
        Gate::authorize('view', $mission);

        return new MissionResource($mission->load(['client', 'auteur']));
    }

    // POST /devs/{username}/missions : recruter un dev directement, sans appli
    public function pourDev(Request $request, string $username)
    {
        $dev = User::query()
            ->where('username', $username)
            ->where('profil', Profil::Dev)
            ->firstOrFail();

        return $this->creer($request, $dev, null);
    }

    // POST /apps/{id}/missions : demander une version sur mesure à l'auteur de l'appli
    public function pourAppli(Request $request, Appli $appli)
    {
        return $this->creer($request, $appli->auteur, $appli->id);
    }

    // POST /missions/{id}/note : le client termine la mission et note l'auteur, une seule fois
    public function note(Request $request, Mission $mission, ProfilCalcule $profils)
    {
        Gate::authorize('noter', $mission);
        abort_if($mission->statut === 'terminee', 409);

        $donnees = $request->validate([
            'note' => ['required', 'integer', 'between:1,5'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ]);

        // Mise à jour conditionnelle : deux notes envoyées au même instant ne passent pas toutes les deux.
        $notee = Mission::query()
            ->whereKey($mission->id)
            ->where('statut', 'en_cours')
            ->update([
                'statut' => 'terminee',
                'note' => $donnees['note'],
                'note_commentaire' => $donnees['commentaire'] ?? null,
                'terminee_le' => now(),
            ]);

        abort_if($notee === 0, 409);

        $mission->refresh()->load(['client', 'auteur']);
        $profils->rafraichir($mission->auteur);

        return new MissionResource($mission);
    }

    private function creer(Request $request, User $auteur, ?int $appId)
    {
        $donnees = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'budget' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'delai' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if ($auteur->is($request->user())) {
            throw ValidationException::withMessages([
                'auteur' => 'Vous ne pouvez pas vous envoyer une mission.',
            ]);
        }

        // Client, auteur et appli viennent de la session et de l'URL, jamais du corps de la requête.
        $mission = Mission::create([
            'client_id' => $request->user()->id,
            'auteur_id' => $auteur->id,
            'app_id' => $appId,
            'message' => $donnees['message'],
            'budget' => $donnees['budget'] ?? null,
            'delai' => $donnees['delai'] ?? null,
            'statut' => 'en_cours',
        ]);

        return (new MissionResource($mission->load(['client', 'auteur'])))
            ->response()
            ->setStatusCode(201);
    }
}
