<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Pagination;
use App\Models\Signalement;
use App\Notifications\AlerteAdmin;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Signalements de modération : un membre signale un contenu ou un comportement, l'équipe
 * traite (après avoir agi : suppression, suspension) ou rejette. Les motifs graves
 * (hameçonnage, minage, contenu illégal) préviennent l'équipe aussitôt par e-mail.
 */
final class SignalementController extends Controller
{
    /** Table de chaque cible, pour vérifier qu'elle existe avant d'accepter le signalement. */
    private const TABLES = [
        'discussion' => 'discussions',
        'message' => 'messages',
        'appli' => 'applis',
        'copie' => 'deploiements',
        'compte' => 'users',
        'evenement' => 'evenements',
    ];

    /** Motifs qui préviennent l'équipe aussitôt : une copie de minage ou d'hameçonnage doit tomber vite. */
    private const MOTIFS_URGENTS = ['hameconnage', 'minage', 'contenu_illegal'];

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'cible' => ['required', 'string', Rule::in(Signalement::CIBLES)],
            'cible_id' => ['required', 'integer', 'min:1'],
            'motif' => ['required', 'string', Rule::in(Signalement::MOTIFS)],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! DB::table(self::TABLES[$donnees['cible']])->where('id', $donnees['cible_id'])->exists()) {
            throw ValidationException::withMessages(['cible_id' => 'Cette cible n’existe pas ou plus.']);
        }

        $existe = Signalement::query()
            ->where('user_id', $request->user()->id)
            ->where('cible', $donnees['cible'])
            ->where('cible_id', $donnees['cible_id'])
            ->where('statut', 'ouvert')
            ->exists();

        if ($existe) {
            throw new ConflictHttpException('Vous avez déjà signalé ce contenu : l’équipe l’examine.');
        }

        // Le contrôle ci-dessus ne voit pas un doublon créé entre-temps (double-clic,
        // deux onglets) : l'index unique partiel tranche, et sa violation rend le même 409.
        try {
            $signalement = Signalement::query()->create([...$donnees, 'user_id' => $request->user()->id]);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Vous avez déjà signalé ce contenu : l’équipe l’examine.');
        }
        $signalement->refresh();

        if (in_array($signalement->motif, self::MOTIFS_URGENTS, true)) {
            AlerteAdmin::envoyer(
                "Signalement {$signalement->motif} : {$signalement->cible} #{$signalement->cible_id}",
                [$signalement->cibleResume(), 'Motif : '.$signalement->motif, 'À examiner dans l’administration.'],
                '/admin/moderation',
            );
        }

        return response()->json($signalement->versApi(), 201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['statut' => ['sometimes', 'string', Rule::in(Signalement::STATUTS)]]);

        return JsonResource::collection(Signalement::query()
            ->when($request->filled('statut'), fn ($requete) => $requete->where('statut', $request->string('statut')->toString()))
            ->with(['signalePar', 'traitePar'])
            ->latest('id')
            ->paginate(Pagination::parPage($request))
            ->withQueryString()
            ->through(fn (Signalement $signalement) => $signalement->versApi()));
    }

    public function show(Signalement $signalement): JsonResponse
    {
        return response()->json($signalement->versApi());
    }

    public function traiter(Request $request, Signalement $signalement): JsonResponse
    {
        $donnees = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        // Le verrou ne tient que dans une transaction : deux admins concurrents ne
        // décident pas tous deux sur un signalement encore ouvert.
        $signalement = DB::transaction(function () use ($signalement, $donnees, $request): Signalement {
            $verrouille = Signalement::query()->lockForUpdate()->findOrFail($signalement->id);

            if ($verrouille->statut !== 'ouvert') {
                throw new ConflictHttpException('Ce signalement est déjà '.$verrouille->statut.'.');
            }

            $verrouille->forceFill([
                'statut' => 'traite',
                'traite_par' => $request->user()->id,
                'traite_le' => now(),
                'note' => $donnees['note'] ?? null,
            ])->save();

            return $verrouille;
        });

        return response()->json($signalement->versApi());
    }

    public function rejeter(Request $request, Signalement $signalement): JsonResponse
    {
        $donnees = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $signalement = DB::transaction(function () use ($signalement, $donnees, $request): Signalement {
            $verrouille = Signalement::query()->lockForUpdate()->findOrFail($signalement->id);

            if ($verrouille->statut !== 'ouvert') {
                throw new ConflictHttpException('Ce signalement est déjà '.$verrouille->statut.'.');
            }

            $verrouille->forceFill([
                'statut' => 'rejete',
                'traite_par' => $request->user()->id,
                'traite_le' => now(),
                'note' => $donnees['note'] ?? null,
            ])->save();

            return $verrouille;
        });

        return response()->json($signalement->versApi());
    }
}
