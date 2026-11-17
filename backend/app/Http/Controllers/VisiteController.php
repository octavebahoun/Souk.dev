<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Audience\Mesure;
use App\Models\User;
use App\Models\Visite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Audience du site : le front signale chaque page vue (sans cookie, sans IP gardée), l'équipe en
 * lit le résumé : visiteurs par jour, pages vues, d'où ils viennent, leur pays et leur appareil.
 */
final class VisiteController extends Controller
{
    /**
     * Le front envoie le corps en text/plain (requête simple, sans prévol CORS) : il est lu à la main.
     * Toujours 204 : une page vue refusée (robot, équipe) ne regarde pas le visiteur.
     */
    public function store(Request $request, Mesure $mesure): Response
    {
        $donnees = json_decode((string) $request->getContent(), true);

        if (is_array($donnees)) {
            /** @var User|null $compte */
            $compte = $request->user('sanctum');
            $mesure->enregistrer($request, $donnees, $compte);
        }

        return response()->noContent();
    }

    /** Résumé pour l'équipe, sur 7, 30 ou 90 jours. */
    public function resume(Request $request): JsonResponse
    {
        $jours = (int) ($request->validate(['jours' => ['sometimes', 'integer', 'in:7,30,90']])['jours'] ?? 30);
        $depuis = now()->startOfDay()->subDays($jours - 1);
        $periode = Visite::query()->where('created_at', '>=', $depuis);
        $jour = DB::getDriverName() === 'sqlite' ? 'date(created_at)' : 'created_at::date';

        $parJour = (clone $periode)
            ->selectRaw("{$jour} as jour, count(distinct visiteur) as visiteurs, count(*) as pages")
            ->groupByRaw($jour)
            ->get()
            ->keyBy(fn ($ligne) => (string) $ligne->jour);

        $serie = [];
        for ($date = $depuis->copy(); $date->lte(now()); $date->addDay()) {
            $ligne = $parJour->get($date->toDateString());
            $serie[] = [
                'jour' => $date->toDateString(),
                'visiteurs' => (int) ($ligne->visiteurs ?? 0),
                'pages' => (int) ($ligne->pages ?? 0),
            ];
        }

        $classement = fn (string $colonne, int $nombre = 10) => (clone $periode)
            ->whereNotNull($colonne)
            ->selectRaw("{$colonne} as nom, count(distinct visiteur) as visiteurs, count(*) as pages")
            ->groupBy($colonne)
            ->orderByDesc('visiteurs')
            ->orderByDesc('pages')
            ->limit($nombre)
            ->get()
            ->map(fn ($ligne) => ['nom' => (string) $ligne->nom, 'visiteurs' => (int) $ligne->visiteurs, 'pages' => (int) $ligne->pages])
            ->values();

        $aujourdhui = Visite::query()->where('created_at', '>=', now()->startOfDay());

        return response()->json([
            'jours' => $jours,
            'aujourdhui' => [
                'visiteurs' => (int) (clone $aujourdhui)->distinct()->count('visiteur'),
                'pages' => (int) (clone $aujourdhui)->count(),
            ],
            'periode' => [
                // Somme des visiteurs de chaque jour : l'empreinte change chaque jour, un même
                // visiteur revenu trois jours compte trois fois.
                'visiteurs' => array_sum(array_column($serie, 'visiteurs')),
                'pages' => array_sum(array_column($serie, 'pages')),
            ],
            'par_jour' => $serie,
            'pages' => $classement('chemin'),
            'sources' => $classement('referent'),
            'campagnes' => $classement('source'),
            'pays' => $classement('pays'),
            'appareils' => $classement('appareil', 4),
            'depuis' => Carbon::parse($depuis)->toDateString(),
        ]);
    }
}
