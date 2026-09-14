<?php

namespace App\Providers;

use App\Support\ChoraleCourante;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
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

        $this->activerModeTunnel();
    }

    /**
     * Mode tunnel : l'application est jointe depuis l'extérieur par une adresse
     * publique en https, alors qu'elle tourne en local en http.
     *
     * Sans ces deux lignes, tous les liens fabriqués par Laravel — et donc
     * chaque lecteur audio — pointeraient vers http://localhost:8000. Ça
     * fonctionne sur la machine qui héberge, et sur aucun téléphone.
     */
    protected function activerModeTunnel(): void
    {
        $adresse = (string) config('chorabase.tunnel_url');

        if ($adresse === '') {
            return;
        }

        URL::forceRootUrl($adresse);
        URL::forceScheme('https');
    }
}
