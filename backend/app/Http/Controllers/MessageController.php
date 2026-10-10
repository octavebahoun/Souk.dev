<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Echange\Diffuseur;
use App\Events\MessageCree;
use App\Events\MessageModifie;
use App\Events\MessageSupprime;
use App\Http\Pagination;
use App\Http\Requests\MessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Discussion;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class MessageController extends Controller
{
    public function index(Discussion $discussion): AnonymousResourceCollection
    {
        $parPage = Pagination::parPage(request());

        $messages = $discussion->messages()
            ->with('auteur')
            ->oldest()
            ->paginate($parPage)
            ->withQueryString();

        return MessageResource::collection($messages);
    }

    public function store(MessageRequest $request, Discussion $discussion, Diffuseur $diffuseur): JsonResponse
    {
        /** @var User $auteur */
        $auteur = $request->user();

        $message = new Message;
        $message->discussion_id = $discussion->id;
        $message->user_id = $auteur->id;
        $message->texte = $request->string('texte')->toString();
        $message->save();
        $message->setRelation('auteur', $auteur);

        $diffuseur->envoyer(new MessageCree($message));

        return (new MessageResource($message))->response()->setStatusCode(201);
    }

    public function update(MessageRequest $request, Message $message, Diffuseur $diffuseur): MessageResource
    {
        Gate::authorize('update', $message);

        $message->texte = $request->string('texte')->toString();
        $message->save();
        $message->load('auteur');
        $diffuseur->envoyer(new MessageModifie($message));

        return new MessageResource($message);
    }

    public function destroy(Message $message, Diffuseur $diffuseur): Response
    {
        Gate::authorize('delete', $message);

        $id = $message->id;
        $discussionId = $message->discussion_id;
        $message->delete();
        $diffuseur->envoyer(new MessageSupprime($id, $discussionId));

        return response()->noContent();
    }
}
