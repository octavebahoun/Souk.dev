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
}