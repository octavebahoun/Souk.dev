<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SauvegarderCopie;
use App\Models\Deploiement;
use App\Moteur\SauvegardeVolumes;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use PharData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sauvegardes d'une copie, pour son client : la liste, une sauvegarde à la demande, et l'export
 * d'une sauvegarde en archive (base du backend intégré au format pg_dump, volumes en tar.gz).
 */
final class SauvegardeController extends Controller
{
    public function index(Deploiement $deploiement, SauvegardeVolumes $sauvegardes): JsonResponse
    {
        Gate::authorize('mettreAJour', $deploiement);

        return response()->json(['data' => $deploiement->copie !== null ? $sauvegardes->liste((string) $deploiement->copie) : []]);
    }

    public function store(Deploiement $deploiement): JsonResponse
    {
        Gate::authorize('mettreAJour', $deploiement);
        if ($deploiement->type !== 'client' || $deploiement->etat !== 'en_ligne' || $deploiement->copie === null) {
            throw new ConflictHttpException('Seule une copie en ligne se sauvegarde.');
        }

        SauvegarderCopie::dispatch($deploiement->id, 'manuelle');

        return response()->json(null, 202);
    }

    public function show(Deploiement $deploiement, string $sauvegarde, SauvegardeVolumes $sauvegardes): BinaryFileResponse
    {
        Gate::authorize('mettreAJour', $deploiement);
        $connue = collect($deploiement->copie !== null ? $sauvegardes->liste((string) $deploiement->copie) : [])->firstWhere('identifiant', $sauvegarde);
        if ($connue === null) {
            throw new NotFoundHttpException('Cette sauvegarde n’existe pas, ou elle a plus de 7 jours.');
        }

        // Une archive tar des fichiers de la sauvegarde, construite à la demande puis effacée.
        $dossier = $sauvegardes->dossier((string) $deploiement->copie, $sauvegarde);
        $archive = sys_get_temp_dir().DIRECTORY_SEPARATOR.'export-'.bin2hex(random_bytes(8)).'.tar';
        (new PharData($archive))->buildFromDirectory($dossier);
        $nom = 'sauvegarde-'.str($deploiement->versApi()['app_nom'])->slug().'-'.$sauvegarde.'.tar';

        return response()->download($archive, $nom, ['Content-Type' => 'application/x-tar'])->deleteFileAfterSend();
    }
}
