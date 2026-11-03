<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte suspendu par l'équipe reçoit 403 sur l'API (avec le motif), sauf sur la lecture
 * de son propre compte et la déconnexion : il doit comprendre pourquoi, et pouvoir partir.
 */
class CompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $compte = $request->user();

        if ($compte !== null && $compte->estSuspendu()
            && ! $request->is('api/me', 'logout')) {
            return response()->json([
                'message' => 'Compte suspendu : '.(string) $compte->suspension_motif,
            ], 403);
        }

        return $next($request);
    }
}
