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

        // Mode tunnel : cloudflared se place devant l'application et lui
        // transmet l'en-tête indiquant que le visiteur est arrivé en https.
        // Sans cette confiance accordée, Laravel se croit en http et pose
        // des cookies de session non sécurisés que le navigateur refuse.
        //
        // Volontairement conditionné : faire confiance à tous les proxys
        // n'a de sens que derrière un tunnel maîtrisé, jamais en production
        // sur un hébergement classique.
        if (env('TUNNEL_URL')) {
            $middleware->trustProxies(at: '*');
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
