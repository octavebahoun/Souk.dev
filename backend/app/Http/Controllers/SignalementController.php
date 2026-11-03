<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Pagination;
use App\Models\Deploiement;
use App\Models\Signalement;
use App\Notifications\AlerteAdmin;
use App\Securite\SuspensionAbus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Politique d'abus, hameçonnage : une copie est suspendue d'office quand des membres distincts,
     * inscrits depuis assez longtemps, la signalent (un seul compte, ou des comptes créés pour
     * l'occasion, ne font pas tomber la copie d'un client). Chaque membre ne compte que pour
     * 3 signalements par jour, et la plateforme ne suspend d'office que 10 copies par jour.
     */
    private const SIGNALEMENTS_DISTINCTS = 2;

    private const ANCIENNETE_JOURS = 7;

    private const SIGNALEMENTS_PAR_MEMBRE_ET_PAR_JOUR = 3;

    private const SUSPENSIONS_PAR_JOUR = 10;

    public function store(Request $request, SuspensionAbus $suspension): JsonResponse
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
            throw new ConflictHttpException('Vous avez déjà signalé ce contenu : l’équipe l’examine.');
        }

        // Le contrôle ci-dessus ne voit pas un doublon créé entre-temps (double-clic,
        // deux onglets) : l'index unique partiel tranche, et sa violation rend le même 409.
        try {
            $signalement = Signalement::query()->create([...$donnees, 'user_id' => $request->user()->id]);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Vous avez déjà signalé ce contenu : l’équipe l’examine.');
        }
        $signalement->refresh();

        $suspendue = $this->suspendreSiHameconnage($signalement, $suspension);

        if (in_array($signalement->motif, self::MOTIFS_URGENTS, true)) {
            AlerteAdmin::envoyer(
                "Signalement {$signalement->motif} : {$signalement->cible} #{$signalement->cible_id}",
                [
                    $signalement->cibleResume(),
                    'Motif : '.$signalement->motif,
                    $suspendue
                        ? 'La copie est suspendue en attendant votre examen (politique d’abus) : réactivez-la si le signalement n’est pas fondé.'
                        : 'À examiner dans l’administration.',
                ],
                '/admin/moderation',
            );
        }

        return response()->json($signalement->versApi(), 201);
    }

    /**
     * Politique d'abus : une copie signalée pour hameçonnage est suspendue aussitôt (ses visiteurs
     * se font voler leurs identifiants pendant qu'on examine), l'équipe tranche ensuite.
     */
    private function suspendreSiHameconnage(Signalement $signalement, SuspensionAbus $suspension): bool
    {
        if ($signalement->cible !== 'copie' || $signalement->motif !== 'hameconnage') {
            return false;
        }

        $deploiement = Deploiement::query()->find($signalement->cible_id);

        if ($deploiement === null) {
            return false;
        }

        // Signalements ouverts sur cette copie, d'auteurs distincts, anciens et pas en rafale.
        $temoins = Signalement::query()
            ->where('cible', 'copie')
            ->where('cible_id', $deploiement->id)
            ->where('motif', 'hameconnage')
            ->where('statut', 'ouvert')
            ->whereHas('signalePar', fn ($compte) => $compte->where('created_at', '<=', now()->subDays(self::ANCIENNETE_JOURS)))
            ->pluck('user_id')
            ->unique()
            ->filter(fn (int $membre) => Signalement::query()
                ->where('user_id', $membre)
                ->where('motif', 'hameconnage')
                ->where('created_at', '>=', now()->subDay())
                ->count() <= self::SIGNALEMENTS_PAR_MEMBRE_ET_PAR_JOUR);

        if ($temoins->count() < self::SIGNALEMENTS_DISTINCTS) {
            return false;
        }

        $cle = 'abus:suspensions-hameconnage:'.now()->toDateString();

        if ((int) Cache::get($cle, 0) >= self::SUSPENSIONS_PAR_JOUR) {
            return false;
        }

        if ($suspension->suspendre($deploiement, 'Hameçonnage signalé : copie suspendue en attendant l’examen de l’équipe.') !== SuspensionAbus::SUSPENDUE) {
            return false;
        }

        Cache::put($cle, (int) Cache::get($cle, 0) + 1, now()->addDay());

        return true;
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
