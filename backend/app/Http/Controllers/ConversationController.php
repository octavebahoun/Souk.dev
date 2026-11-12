<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Echange\Diffuseur;
use App\Events\ConversationModifiee;
use App\Events\MessagePriveCree;
use App\Http\Pagination;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\InvitationAcceptee;
use App\Notifications\InvitationRecue;
use App\Notifications\NouveauMessagePrive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Échange privé entre deux devs. Tout commence par une invitation, avec un mot d'introduction :
 * tant que l'invité n'a pas accepté, personne n'écrit. À mille devs, chacun garde la main sur
 * qui lui parle.
 */
final class ConversationController extends Controller
{
    /** Mes conversations : invitations reçues d'abord, puis les plus récemment actives. */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $compte */
        $compte = $request->user();

        $conversations = Conversation::query()
            ->de($compte)
            // Une invitation déclinée ne reste visible que pour celui qui l'a envoyée.
            ->where(fn ($visible) => $visible->where('statut', '!=', Conversation::REFUSEE)->orWhere('demandeur_id', $compte->id))
            ->with(['demandeur', 'destinataire', 'discussion', 'dernierMessage'])
            ->orderByRaw('case when statut = ? and destinataire_id = ? then 0 else 1 end', [Conversation::EN_ATTENTE, $compte->id])
            ->orderByRaw('coalesce(dernier_message_le, repondu_le, created_at) desc')
            ->orderByDesc('id')
            ->paginate(Pagination::parPage($request))
            ->withQueryString();

        return ConversationResource::collection($conversations);
    }

    /** Inviter un dev à échanger, depuis son profil ou une discussion. */
    public function inviter(Request $request, string $username, Diffuseur $diffuseur): JsonResponse
    {
        /** @var User $compte */
        $compte = $request->user();
        $invite = User::query()->where('username', $username)->whereNull('suspendu_le')->firstOrFail();

        $donnees = $request->validate([
            'message' => ['required', 'string', 'min:10', 'max:500'],
            'discussion_id' => ['sometimes', 'nullable', 'integer', 'exists:discussions,id'],
        ], [
            'message.required' => 'Écrivez un mot d’introduction : qui vous êtes, pourquoi vous écrivez.',
            'message.min' => 'Le mot d’introduction doit faire au moins 10 caractères.',
            'message.max' => 'Le mot d’introduction ne peut pas dépasser 500 caractères.',
            'discussion_id.exists' => 'Cette discussion n’existe pas.',
        ]);

        if ($invite->id === $compte->id) {
            throw ValidationException::withMessages(['message' => 'Vous ne pouvez pas vous inviter vous-même.']);
        }

        $conversation = DB::transaction(function () use ($compte, $invite, $donnees): Conversation {
            $existante = Conversation::entre($compte, $invite);

            if ($existante !== null && ! $existante->peutEtreRelancee()) {
                throw new ConflictHttpException(match ($existante->statut) {
                    Conversation::ACCEPTEE => 'Vous échangez déjà avec ce dev : retrouvez la conversation dans vos messages.',
                    Conversation::EN_ATTENTE => 'Une invitation attend déjà une réponse entre vous deux.',
                    default => 'Ce dev a décliné une invitation récente. Vous pourrez le réinviter plus tard.',
                });
            }

            $conversation = $existante ?? new Conversation;
            $conversation->forceFill([
                'demandeur_id' => $compte->id,
                'destinataire_id' => $invite->id,
                'discussion_id' => $donnees['discussion_id'] ?? null,
                'statut' => Conversation::EN_ATTENTE,
                'invitation' => $donnees['message'],
                'repondu_le' => null,
            ])->save();

            return $conversation;
        });

        $diffuseur->envoyer(new ConversationModifiee($conversation, $invite));
        $invite->notify(new InvitationRecue($conversation));

        return response()->json(ConversationResource::pour($conversation, $compte), 201);
    }

    /** L'invité accepte ou décline. Un refus n'est pas notifié : il n'a pas à se justifier. */
    public function repondre(Request $request, Conversation $conversation, Diffuseur $diffuseur): JsonResponse
    {
        Gate::authorize('repondre', $conversation);

        $donnees = $request->validate(['accepter' => ['required', 'boolean']], ['accepter.required' => 'Indiquez si vous acceptez l’invitation.']);

        if ($conversation->statut !== Conversation::EN_ATTENTE) {
            throw new ConflictHttpException('Vous avez déjà répondu à cette invitation.');
        }

        $conversation->forceFill([
            'statut' => $donnees['accepter'] ? Conversation::ACCEPTEE : Conversation::REFUSEE,
            'repondu_le' => now(),
        ])->save();

        /** @var User $compte */
        $compte = $request->user();
        $demandeur = $conversation->demandeur;

        if ($demandeur !== null) {
            $diffuseur->envoyer(new ConversationModifiee($conversation, $demandeur));

            if ($conversation->estAcceptee()) {
                $demandeur->notify(new InvitationAcceptee($conversation));
            }
        }

        return response()->json(ConversationResource::pour($conversation, $compte));
    }

    /** Les messages, du plus ancien au plus récent ; les lire marque la conversation comme lue. */
    public function messages(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        /** @var User $compte */
        $compte = $request->user();
        $conversation->marquerLue($compte);

        $messages = $conversation->messages()
            ->with('auteur')
            ->oldest()
            ->orderBy('id')
            ->paginate(Pagination::parPage($request));

        return MessageResource::collection($messages);
    }

    public function ecrire(Request $request, Conversation $conversation, Diffuseur $diffuseur): JsonResponse
    {
        Gate::authorize('ecrire', $conversation);

        $donnees = $request->validate(['texte' => ['required', 'string', 'max:5000']], [
            'texte.required' => 'Écrivez votre message.',
            'texte.max' => 'Un message ne peut pas dépasser 5000 caractères.',
        ]);

        /** @var User $compte */
        $compte = $request->user();

        $message = $conversation->messages()->create(['auteur_id' => $compte->id, 'texte' => $donnees['texte']]);
        $message->setRelation('auteur', $compte);
        $conversation->forceFill(['dernier_message_le' => $message->created_at])->save();
        $conversation->marquerLue($compte);

        $autre = $conversation->autre($compte);

        if ($autre !== null && ! $autre->estSuspendu()) {
            $diffuseur->envoyer(new MessagePriveCree($message, $autre));
            $autre->notify(new NouveauMessagePrive($message));
        }

        return (new MessageResource($message))->response()->setStatusCode(201);
    }
}
