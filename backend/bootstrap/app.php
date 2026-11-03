<?php

declare(strict_types=1);

use App\Http\Middleware\Administrateur;
use App\Http\Middleware\CompteActif;
use App\Http\Middleware\CorsCopies;
use App\Http\Middleware\Idempotence;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Env;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// Secrets internes générés au premier démarrage de docker compose (service « secrets »).
// Ils passent avant backend/.env ; une vraie variable d'environnement passe avant eux.
if (is_readable('/run/soukdev/plateforme.env')) {
    Dotenv\Dotenv::create(Env::getRepository(), '/run/soukdev', 'plateforme.env')->safeLoad();
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Traefik (production) joint l'API par son port publié sur 127.0.0.1 : la requête arrive de
        // la passerelle Docker. Sans cette confiance, tous les visiteurs auraient la même adresse
        // (une seule limite de débit pour tous) et les liens signés seraient lus en http://.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
        );
        // La connexion des utilisateurs d'une appli (/api/copies/*) a sa propre règle d'origine,
        // sans cookies : le CORS de l'API (avec cookies, origines listées) ne s'y applique pas.
        HandleCors::skipWhen(fn (Request $request) => $request->is('api/copies/*'));
        $middleware->prepend(CorsCopies::class);
        $middleware->statefulApi();
        // Pas de page de connexion côté API : un visiteur reçoit 401 en JSON, même sans
        // Accept: application/json (sinon Laravel cherche une route « login » et répond 500).
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*', 'broadcasting/*')
            ? null
            : rtrim((string) config('app.frontend_url'), '/').'/connexion');
        $middleware->api(append: [
            'throttle:60,1',
        ]);
        $middleware->alias([
            'idempotence' => Idempotence::class,
            'administrateur' => Administrateur::class,
            'compte.actif' => CompteActif::class,
        ]);
        // Le contrôle d'admin passe avant la recherche de l'objet de l'URL (403 plutôt que 404).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: Administrateur::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $json = function (string $message, int $status, array $headers = []) {
            return response()->json(['message' => $message], $status, $headers);
        };

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($json) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $json('Non connecté.', 401);
            }
        });

        // Laravel convertit un refus de Policy en HttpException (403, ou 404 avec denyAsNotFound)
        // avant ces rappels : on traduit ses messages par défaut et on garde la raison d'un refus.
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($json) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $message = $exception->getMessage();

            return match ($exception->getStatusCode()) {
                403 => $json($message === '' || $message === 'This action is unauthorized.' ? 'Action non autorisée.' : $message, 403),
                404 => $json('Introuvable.', 404),
                default => null,
            };
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($json) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $json('Introuvable.', 404);
            }
        });

        $exceptions->render(function (TokenMismatchException $exception, Request $request) use ($json) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $json('Jeton CSRF absent ou expiré.', 419);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) use ($json) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $json('Limite de débit atteinte.', 429, $exception->getHeaders());
            }
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($json) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            if ($status < 500) {
                return null;
            }

            // Un service momentanément indisponible dit pourquoi et quand réessayer ; une erreur interne reste muette.
            if ($exception instanceof HttpExceptionInterface && $status === 503 && $exception->getMessage() !== '') {
                return $json($exception->getMessage(), 503, $exception->getHeaders());
            }

            return $json('Le serveur a rencontré un problème. Réessayez dans quelques instants.', $status);
        });
    })->create();
