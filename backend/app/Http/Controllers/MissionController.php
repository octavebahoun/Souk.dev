<?php

namespace App\Http\Controllers;

use App\Http\Resources\MissionResource;
use App\Models\Mission;
use Illuminate\Http\Request;

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
        abort_unless($mission->concerne($request->user()), 403);

        return new MissionResource($mission->load(['client', 'auteur']));
    }

    // POST /missions/{id}/note : le client termine la mission et note l'auteur, une seule fois
    public function note(Request $request, Mission $mission)
    {
        abort_unless($request->user()->id === $mission->client_id, 403);
        abort_if($mission->statut === 'terminee', 409);

        $donnees = $request->validate([
            'note' => ['required', 'integer', 'between:1,5'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ]);

        $mission->update([
            'statut' => 'terminee',
            'note' => $donnees['note'],
            'note_commentaire' => $donnees['commentaire'] ?? null,
        ]);

        return new MissionResource($mission->load(['client', 'auteur']));
    }
}
