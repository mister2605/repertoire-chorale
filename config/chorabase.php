<?php

return [

    /*
     * Adresse du PWA (le front Vue). Sert à construire les liens d'invitation
     * et le lien d'adhésion. En production, Laravel sert aussi le PWA : c'est
     * donc la même adresse que le site.
     *
     * Se règle avec FRONTEND_URL dans le .env.
     */
    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost:8000')),

    /*
     * MODE TUNNEL — pour tester avec de vraies personnes sans hébergement.
     *
     * cloudflared donne une adresse publique en https qui pointe vers ton
     * ordinateur. Laravel, lui, voit une requête arrivant sur "localhost" :
     * sans cette valeur, il fabriquerait des liens audio en
     * http://localhost:8000, injoignables depuis un téléphone.
     *
     * Ne la remplis jamais à la main : la commande
     *   php artisan chorabase:tunnel <adresse>
     * s'en charge, et met aussi à jour les domaines de session.
     *
     * À VIDER avant de passer sur un vrai hébergement.
     */
    'tunnel_url' => env('TUNNEL_URL'),

    /*
     * Taille maximale d'un fichier audio, en kilo-octets.
     *
     * Attention : cette limite ne sert à rien si le php.ini est plus strict.
     * PHP refuse le fichier AVANT que Laravel le voie. Vérifie avec :
     *   php -i | findstr /i "upload_max_filesize post_max_size"
     */
    'taille_max_audio_ko' => (int) env('TAILLE_MAX_AUDIO_KO', 20480),

];
