<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Profil;
use App\Http\Pagination;
use App\Models\User;
use App\Notifications\CompteSuspendu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Comptes vus par l'équipe : liste cherchable, suspension (le compte ne peut plus se connecter
 * ni appeler l'API, avec le motif) et levée. L'équipe ne suspend jamais son propre compte :
 * elle se couperait l'accès à l'administration.
 */
final class AdminComptesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'recherche' => ['sometimes', 'string', 'max:100'],
            'suspendus' => ['sometimes', 'boolean'],
        ]);

        return JsonResource::collection(User::query()
            ->when($request->filled('recherche'), function ($requete) use ($request): void {
                $terme = '%'.(string) $request->string('recherche').'%';
                $requete->where(fn ($sous) => $sous
                    ->where('name', 'like', $terme)
                    ->orWhere('username', 'like', $terme)
                    ->orWhere('email', 'like', $terme));
            })
            ->when($request->boolean('suspendus'), fn ($requete) => $requete->whereNotNull('suspendu_le'))
            ->latest('id')
            ->paginate(Pagination::parPage($request))
            ->withQueryString()
            ->through(fn (User $compte) => self::champs($compte)));
    }

    public function suspendre(Request $request, User $compte): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);

        if ($compte->id === $request->user()->id) {
            throw new ConflictHttpException('L’équipe ne suspend pas son propre compte.');
        }

        if ($compte->estSuspendu()) {
            throw new ConflictHttpException('Ce compte est déjà suspendu.');
        }

        $compte->forceFill(['suspendu_le' => now(), 'suspension_motif' => $donnees['motif']])->save();
        $compte->notify(new CompteSuspendu($donnees['motif']));

        return response()->json(self::champs($compte->refresh()));
    }

    public function leverSuspension(User $compte): JsonResponse
    {
        if (! $compte->estSuspendu()) {
            throw new ConflictHttpException('Ce compte n’est pas suspendu.');
        }

        $compte->forceFill(['suspendu_le' => null, 'suspension_motif' => null])->save();

        return response()->json(self::champs($compte->refresh()));
    }

    /**
     * @return array<string, mixed>
     */
    public static function champs(User $compte): array
    {
        return [
            'id' => $compte->id,
            'nom' => $compte->name,
            'username' => $compte->username,
            'email' => $compte->email,
            'profil' => $compte->profil instanceof Profil ? $compte->profil->value : $compte->profil,
            'est_admin' => $compte->est_admin,
            'cree_le' => $compte->created_at->toIso8601String(),
            'suspendu_le' => $compte->suspendu_le?->toIso8601String(),
            'suspension_motif' => $compte->suspension_motif,
        ];
    }
}
