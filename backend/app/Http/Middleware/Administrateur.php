<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes de l'administration : réservé à l'équipe, vérifié avant de chercher l'objet de l'URL
 * (un compte ordinaire reçoit toujours 403, jamais un 404 qui dirait qu'un identifiant existe).
 */
class Administrateur
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->est_admin !== true) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        return $next($request);
    }
}
