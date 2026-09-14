<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\CelebrationController;
use App\Http\Controllers\ChantController;
use App\Http\Controllers\ChantVersionController;
use App\Http\Controllers\ChoraleController;
use App\Http\Controllers\MembreController;
use App\Http\Controllers\PupitreController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    // Réglages de la chorale : pupitres et catégories (PupitrePolicy, CategoriePolicy).
    // Tout membre peut lire la liste (elle sert aux filtres), seul le maître
    // de chœur peut la modifier.
    Route::get('/pupitres', [PupitreController::class, 'index']);
    Route::post('/pupitres', [PupitreController::class, 'store']);
    Route::put('/pupitres/{pupitre}', [PupitreController::class, 'update']);
    Route::delete('/pupitres/{pupitre}', [PupitreController::class, 'destroy']);

    Route::get('/categories', [CategorieController::class, 'index']);
    Route::post('/categories', [CategorieController::class, 'store']);
    Route::put('/categories/{categorie}', [CategorieController::class, 'update']);
    Route::delete('/categories/{categorie}', [CategorieController::class, 'destroy']);

    // Les droits ne sont plus portés par la route mais par les Policies :
    // impossible d'ajouter un endpoint en oubliant de le protéger.
    Route::get('/chants', [ChantController::class, 'index']);
    Route::get('/chants/{chant}', [ChantController::class, 'show']);
    Route::post('/chants', [ChantController::class, 'store']);
    Route::put('/chants/{chant}', [ChantController::class, 'update']);
    Route::delete('/chants/{chant}', [ChantController::class, 'destroy']);

    // Historique des paroles
    Route::get('/chants/{chant}/versions', [ChantVersionController::class, 'index']);
    Route::post('/chants/{chant}/versions/{version}/restaurer', [ChantVersionController::class, 'restaurer']);

    // Comptes des membres (UserPolicy)
    Route::get('/membres', [MembreController::class, 'index']);
    Route::post('/membres', [MembreController::class, 'store']);
    Route::put('/membres/{membre}', [MembreController::class, 'update']);
    Route::delete('/membres/{membre}', [MembreController::class, 'destroy']);
    Route::post('/membres/{membre}/invitation', [MembreController::class, 'invitation']);

    // Programme des célébrations (CelebrationPolicy).
    // Le maître de chœur est maître du programme ; l'instrumentiste ne peut
    // qu'annoter une ligne, via la dernière route — volontairement étroite.
    Route::get('/celebrations', [CelebrationController::class, 'index']);
    Route::get('/celebrations/{celebration}', [CelebrationController::class, 'show']);
    Route::post('/celebrations', [CelebrationController::class, 'store']);
    Route::put('/celebrations/{celebration}', [CelebrationController::class, 'update']);
    Route::delete('/celebrations/{celebration}', [CelebrationController::class, 'destroy']);
    Route::patch(
        '/celebrations/{celebration}/items/{item}/note-instrument',
        [CelebrationController::class, 'annoter']
    );

    // Réglages de la chorale : lien d'adhésion
    Route::get('/chorale', [ChoraleController::class, 'show']);
    Route::put('/chorale', [ChoraleController::class, 'update']);
    Route::post('/chorale/adhesion', [ChoraleController::class, 'basculerAdhesion']);
    Route::post('/chorale/adhesion/regenerer', [ChoraleController::class, 'regenererAdhesion']);
});
