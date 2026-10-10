<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->api(append: [
            'throttle:60,1',
        ]);
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

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($json) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $json('Action non autorisée.', 403);
            }
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
    })->create();
