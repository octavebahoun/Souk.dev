<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La connexion des utilisateurs d'une appli (/api/copies/*) est appelée depuis le navigateur, sur
 * le domaine de la copie : toute origine est acceptée, jamais avec les cookies de Souk.dev (ces
 * routes n'en lisent aucun). Le reste de l'API garde sa liste d'origines avec cookies.
 */
class CorsCopies
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/copies/*')) {
            return $next($request);
        }

        $entetes = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept',
            'Access-Control-Max-Age' => '600',
        ];

        if ($request->isMethod('OPTIONS')) {
            return response()->noContent()->withHeaders($entetes);
        }

        return $next($request)->withHeaders($entetes);
    }
}
