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
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * « Demander l'avis de Souk AI » : l'auteur d'un bug consent à envoyer sa fiche chez le
 * fournisseur ; trois analyses par 24 heures (seules les pannes du fournisseur sont rendues, et
 * six tentatives au plus) ; une à la fois.
 */
final class AnalyseIaController extends Controller
{
    /** Tentatives sur 24 heures, pannes du fournisseur comprises : la dépense a toujours un plafond. */
    private const TENTATIVES_PAR_JOUR = 6;

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

            $enCours = AnalyseIa::query()
                ->where('discussion_id', $discussion->id)
                ->where('statut', 'en_cours')
                ->where('created_at', '>', now()->subMinutes(AnalyseIa::MINUTES_AVANT_ABANDON))
                ->exists();
            if ($enCours) {
                throw new ConflictHttpException('Une analyse de ce bug est déjà en cours.');
            }

            // Les analyses comptent même si leur discussion a été supprimée ou leur fiche modifiée ;
            // seule une panne du fournisseur (ou de la file) n'est pas comptée.
            $recentes = AnalyseIa::query()
                ->where('user_id', $compte->id)
                ->where('created_at', '>', now()->subDay())
                ->oldest()
                ->get(['created_at', 'statut', 'erreur']);
            $comptees = $recentes->reject(fn (AnalyseIa $une) => $une->statut === 'echec' && in_array($une->erreur, ['indisponible', 'file'], true));
            $bloquantes = $comptees->count() >= $parJour ? $comptees : ($recentes->count() >= self::TENTATIVES_PAR_JOUR ? $recentes : null);
            if ($bloquantes !== null) {
                $attente = max(60, (int) now()->diffInSeconds($bloquantes->first()->created_at->copy()->addDay(), true));

                throw new TooManyRequestsHttpException($attente, "{$parJour} analyses par 24 heures : la prochaine sera possible dans ".self::duree($attente).'.');
            }

            return AnalyseIa::query()->create([
                'discussion_id' => $discussion->id,
                'user_id' => $compte->id,
                'statut' => 'en_cours',
                'consentement_le' => now(),
            ]);
        });

        try {
            AnalyserBug::dispatch($analyse->id);
        } catch (Throwable $erreur) {
            Log::error('souk-ai: file indisponible', ['analyse' => $analyse->id, 'erreur' => class_basename($erreur)]);
            $analyse->forceFill(['statut' => 'echec', 'erreur' => 'file', 'termine_le' => now()])->save();

            throw new ServiceUnavailableHttpException(60, 'Souk AI est momentanément indisponible. Réessayez dans quelques minutes.');
        }

        return response()->json($analyse->versApi(), 202);
    }

    private static function duree(int $secondes): string
    {
        // Arrondi à la minute d'abord : jamais « 1 h 60 ».
        $minutes = (int) ceil($secondes / 60);
        $heures = intdiv($minutes, 60);
        $reste = $minutes % 60;

        return $heures > 0 ? "{$heures} h ".str_pad((string) $reste, 2, '0', STR_PAD_LEFT) : "{$minutes} min";
    }
}
