<?php

namespace App\Http\Controllers;

use App\Echange\Diffuseur;
use App\Events\MessageMissionCree;
use App\Http\Resources\MessageResource;
use App\Models\Mission;
use App\Notifications\NouveauMessageMission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MissionMessageController extends Controller
{
    // GET /missions/{mission}/messages : fil privé, du plus ancien au plus récent
    public function index(Request $request, Mission $mission)
    {
        Gate::authorize('view', $mission);

        $parPage = min(max((int) $request->query('per_page', 20), 1), 50);

        $messages = $mission->messages()
            ->with('auteur')
            ->oldest()
            ->orderBy('id') // départage les messages de la même seconde
            ->paginate($parPage);

        return MessageResource::collection($messages);
    }

    // POST /missions/{mission}/messages : écrire dans le fil privé
    public function store(Request $request, Mission $mission, Diffuseur $diffuseur)
    {
        Gate::authorize('ecrire', $mission);

        $donnees = $request->validate(['texte' => ['required', 'string', 'max:5000']]);

        $message = $mission->messages()->create([
            'auteur_id' => $request->user()->id,
            'texte' => $donnees['texte'],
        ]);
        $diffuseur->envoyer(new MessageMissionCree($message->load('auteur')));
        $destinataire = $request->user()->id === $mission->client_id ? $mission->auteur : $mission->client;
        $destinataire?->notify(new NouveauMessageMission($message));

        return (new MessageResource($message->load('auteur')))
            ->response()
            ->setStatusCode(201);
    }
}
