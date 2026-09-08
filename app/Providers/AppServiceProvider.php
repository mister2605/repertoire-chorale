<?php

namespace App\Providers;

use App\Support\ChoraleCourante;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Une seule instance par requête : tout le monde parle de la même chorale.
        $this->app->singleton(ChoraleCourante::class);
    }

    public function boot(): void
    {
        // Corrige l'erreur "1071 La clé est trop longue" avec MySQL/MariaDB
        // en utf8mb4 sur les versions un peu anciennes (fréquent avec WAMP)
        Schema::defaultStringLength(191);
    }
}
