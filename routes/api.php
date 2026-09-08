<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\ChantController;
use App\Http\Controllers\PupitreController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/pupitres', [PupitreController::class, 'index']);
    Route::get('/categories', [CategorieController::class, 'index']);

    // Les droits ne sont plus portés par la route mais par ChantPolicy :
    // impossible d'ajouter un endpoint en oubliant de le protéger.
    Route::get('/chants', [ChantController::class, 'index']);
    Route::get('/chants/{chant}', [ChantController::class, 'show']);
    Route::post('/chants', [ChantController::class, 'store']);
    Route::put('/chants/{chant}', [ChantController::class, 'update']);
    Route::delete('/chants/{chant}', [ChantController::class, 'destroy']);
});
