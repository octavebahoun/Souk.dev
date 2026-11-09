<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Moteur\RouteurCopies;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Question du proxy TLS placé devant Traefik (Caddy, certificats à la demande) : « puis-je prendre
 * un certificat pour ce nom ? ». Oui (200) seulement pour le sous-domaine d'une copie servie.
 */
class CertificatCopieController extends Controller
{
    public function __invoke(Request $request, RouteurCopies $routeur): Response
    {
        $domaine = $request->query('domain');

        abort_unless(is_string($domaine) && $routeur->sert($domaine), 404);

        return response()->noContent(200);
    }
}
