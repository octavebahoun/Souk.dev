<?php

namespace App\Http\Controllers;

use App\Http\Resources\MessageResource;
use App\Models\Mission;
use Illuminate\Http\Request;

class MissionMessageController extends Controller
{
    // GET /missions/{mission}/messages : fil privé, du plus ancien au plus récent
    public function index(Request $request, Mission $mission)
    {
        abort_unless($mission->concerne($request->user()), 403);

        $parPage = min(max((int) $request->query('per_page', 20), 1), 50);

        $messages = $mission->messages()
            ->with('auteur')
            ->oldest()
            ->orderBy('id') // départage les messages de la même seconde
            ->paginate($parPage);

        return MessageResource::collection($messages);
    }

    // POST /missions/{mission}/messages : écrire dans le fil privé
    public function store(Request $request, Mission $mission)
    {
        abort_unless($mission->concerne($request->user()), 403);

        $donnees = $request->validate(['texte' => ['required', 'string', 'max:5000']]);

        $message = $mission->messages()->create([
            'auteur_id' => $request->user()->id,
            'texte' => $donnees['texte'],
        ]);

        return (new MessageResource($message->load('auteur')))
            ->response()
            ->setStatusCode(201);
    }
}
