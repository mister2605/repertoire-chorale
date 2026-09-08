<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Ces deux routes restent dans web.php (et pas api.php) car c'est ce groupe
// qui vérifie le jeton CSRF envoyé par le PWA après l'appel à /sanctum/csrf-cookie.

// throttle:5,1 = 5 tentatives par minute et par IP.
// Sans ça, une petite base d'emails prévisibles se casse à la force brute.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout']);
