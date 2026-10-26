<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\AnalyserBug;
use App\Models\AnalyseIa;
use App\Models\Discussion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * « Demander l'avis de Souk AI » : l'auteur d'un bug consent à envoyer sa fiche chez le
 * fournisseur ; trois analyses par 24 heures (les échecs ne comptent pas) ; une à la fois.
 */
final class AnalyseIaController extends Controller
{
    public function store(Request $request, Discussion $discussion): JsonResponse
    {
        Gate::authorize('analyser', $discussion);
        $request->validate(
            ['consentement' => ['accepted']],
            ['consentement.accepted' => 'Acceptez que la fiche du bug et son code partent chez DeepSeek pour l’analyse.'],
        );
        if (! in_array('bug', $discussion->etiquettes ?? [], true)) {
            throw ValidationException::withMessages(['etiquettes' => 'Souk AI n’analyse que les discussions avec l’étiquette « bug ».']);
        }

        /** @var User $compte */
        $compte = $request->user();
        $parJour = (int) config('services.ia.par_jour', 3);

        $analyse = DB::transaction(function () use ($discussion, $compte, $parJour): AnalyseIa {
            // Verrou du compte : deux demandes simultanées ne passent pas toutes les deux sous la limite.
            User::query()->whereKey($compte->id)->lockForUpdate()->first();

            if (AnalyseIa::query()->where('discussion_id', $discussion->id)->where('statut', 'en_cours')->exists()) {
                throw new ConflictHttpException('Une analyse de ce bug est déjà en cours.');
            }

            $recentes = AnalyseIa::query()
                ->where('user_id', $compte->id)
                ->where('statut', '!=', 'echec')
                ->where('created_at', '>', now()->subDay())
                ->oldest()
                ->pluck('created_at');
            if ($recentes->count() >= $parJour) {
                $attente = max(60, (int) now()->diffInSeconds($recentes->first()->copy()->addDay(), true));

                throw new TooManyRequestsHttpException($attente, "{$parJour} analyses par 24 heures : la prochaine sera possible dans ".self::duree($attente).'.');
            }

            return AnalyseIa::query()->create([
                'discussion_id' => $discussion->id,
                'user_id' => $compte->id,
                'statut' => 'en_cours',
                'consentement_le' => now(),
            ]);
        });

        AnalyserBug::dispatch($analyse->id)->afterCommit();

        return response()->json($analyse->versApi(), 202);
    }

    private static function duree(int $secondes): string
    {
        $heures = intdiv($secondes, 3600);
        $minutes = (int) ceil(($secondes % 3600) / 60);

        return $heures > 0 ? "{$heures} h ".str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) : "{$minutes} min";
    }
}
