<?php

namespace App\Http\Middleware;

use App\Models\CleIdempotence;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-tête Idempotency-Key sur les routes qui déplacent de l'argent : rejouée avec la même clé
 * et le même corps, la requête renvoie la réponse d'origine (24 heures) ; avec un autre corps,
 * ou pendant que la première tourne, 409. Une erreur serveur libère la clé.
 * « idempotence:obligatoire » refuse une requête sans clé (428).
 */
class Idempotence
{
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $cle = $request->header('Idempotency-Key');

        if ($request->user() === null) {
            return $next($request);
        }

        if ($cle === null) {
            return $mode === 'obligatoire'
                ? response()->json(['message' => 'L’en-tête Idempotency-Key est obligatoire pour cette opération (elle déplace de l’argent).'], 428)
                : $next($request);
        }

        if (preg_match('/^[A-Za-z0-9_-]{8,100}$/', $cle) !== 1) {
            return response()->json(['message' => 'L’en-tête Idempotency-Key est invalide (8 à 100 lettres, chiffres, - ou _).'], 422);
        }

        $portee = ['user_id' => $request->user()->id, 'route' => $request->method().' '.$request->path(), 'cle' => $cle];
        $empreinte = hash('sha256', $request->getContent());

        CleIdempotence::query()->where($portee)->where('expire_le', '<=', now())->delete();
        $existante = CleIdempotence::query()->where($portee)->first();

        if ($existante !== null) {
            return $this->rejouer($existante, $empreinte);
        }

        try {
            $ligne = CleIdempotence::query()->create([...$portee, 'empreinte_corps' => $empreinte, 'expire_le' => now()->addDay()]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Une requête identique est déjà en cours.'], 409);
        }

        $reponse = $next($request);

        if ($reponse->getStatusCode() >= 500) {
            $ligne->delete();

            return $reponse;
        }

        $ligne->forceFill(['statut_http' => $reponse->getStatusCode(), 'reponse' => (string) $reponse->getContent()])->save();

        return $reponse;
    }

    private function rejouer(CleIdempotence $existante, string $empreinte): Response
    {
        if (! hash_equals($existante->empreinte_corps, $empreinte)) {
            return response()->json(['message' => 'Cette clé d’idempotence a déjà servi pour une autre requête.'], 409);
        }

        if ($existante->statut_http === null) {
            return response()->json(['message' => 'Une requête identique est déjà en cours.'], 409);
        }

        return response((string) $existante->reponse, $existante->statut_http)
            ->header('Content-Type', 'application/json')
            ->header('Idempotent-Replayed', 'true');
    }
}
