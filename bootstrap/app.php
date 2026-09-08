<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Sans cette ligne, AUCUNE route de routes/api.php n'est chargée :
        // tous les appels /api/* renvoient 404.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Indispensable pour Sanctum en mode SPA : ajoute au groupe "api"
        // le middleware qui reconnaît le cookie de session du PWA.
        $middleware->statefulApi();

        // Le PWA envoie ses requêtes depuis un autre sous-domaine :
        // Laravel doit accepter les cookies et les en-têtes CSRF.
        $middleware->validateCsrfTokens(except: [
            // rien pour l'instant — tout passe par le jeton CSRF
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
