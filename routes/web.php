<?php

use App\Http\Controllers\AdhesionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

// Ces routes restent dans web.php (et pas api.php) car c'est ce groupe
// qui vérifie le jeton CSRF envoyé par le PWA après l'appel à /sanctum/csrf-cookie.

// throttle:5,1 = 5 tentatives par minute et par IP.
// Sans ça, une petite base d'emails prévisibles se casse à la force brute.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout']);

// Route PUBLIQUE : le choriste ouvre son lien d'invitation nominatif et
// choisit son mot de passe. Limitée en fréquence contre le devinage de jetons.
Route::post('/invitation/definir-mot-de-passe', [InvitationController::class, 'definirMotDePasse'])
    ->middleware('throttle:10,1');

// Routes PUBLIQUES d'adhésion en libre-service (lien unique par chorale).
//
// Ces limites sont comptées PAR ADRESSE IP. Sur un réseau mobile burkinabè,
// tous les choristes d'un même opérateur sortent souvent derrière la MÊME
// adresse publique : trente personnes qui ouvrent le lien après le message
// WhatsApp comptent comme une seule IP. Des limites basses ne bloquaient donc
// pas un attaquant, elles bloquaient la chorale.
//
// Le vrai garde-fou contre le devinage n'est pas le débit : c'est le code
// lui-même (24 caractères aléatoires) et le fait que l'adhésion se referme
// depuis l'écran Membres quand la séance est finie.
Route::get('/adhesion/{code}', [AdhesionController::class, 'infos'])->middleware('throttle:120,1');
Route::post('/adhesion/{code}', [AdhesionController::class, 'rejoindre'])->middleware('throttle:30,1');

/*
 * Le PWA est servi par Laravel, depuis le même domaine que l'API.
 *
 * Pourquoi : l'authentification Sanctum passe par un cookie de session, et un
 * cookie ne traverse pas deux domaines racines différents. Un seul domaine
 * supprime d'un coup le problème de cookie, la configuration CORS et le
 * second hébergement. Un hébergement mutualisé ordinaire suffit alors.
 *
 * Cette route doit rester LA DERNIÈRE du fichier : elle attrape tout ce qui
 * n'a pas déjà été reconnu. Le filtre exclut les chemins qui appartiennent
 * au serveur (api, storage, sanctum, /up) pour qu'ils continuent de répondre
 * normalement.
 */
Route::get('/{chemin?}', function () {
    $spa = public_path('index.html');

    abort_unless(
        file_exists($spa),
        404,
        "L'application n'a pas encore été déployée sur ce serveur. "
        .'Lance "npm run deployer" dans chorabase-pwa, puis envoie le contenu de public/.'
    );

    return response()->file($spa);
})->where('chemin', '^(?!api\/|storage\/|sanctum\/|up$).*$');
