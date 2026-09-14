<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Configure l'application pour une séance de test derrière un tunnel.
 *
 * cloudflared donne une nouvelle adresse à chaque démarrage. Trois valeurs
 * du .env doivent suivre : sans elles, on se connecte mais les appels API
 * sont refusés, et les liens audio pointent vers une machine que personne
 * ne peut joindre. Cette commande les écrit d'un coup.
 *
 *   php artisan chorabase:tunnel https://xxx-yyy-zzz.trycloudflare.com
 *   php artisan chorabase:tunnel --stop
 */
class ConfigurerTunnel extends Command
{
    protected $signature = 'chorabase:tunnel
                            {adresse? : L\'adresse https affichée par cloudflared}
                            {--stop : Repasser en mode local}';

    protected $description = "Règle l'application sur l'adresse publique du tunnel (ou revient en local)";

    public function handle(): int
    {
        if ($this->option('stop')) {
            return $this->appliquer(null);
        }

        $adresse = rtrim((string) $this->argument('adresse'), '/');

        if ($adresse === '') {
            $this->components->error('Donne l\'adresse affichée par cloudflared, ou utilise --stop.');
            $this->line('  Exemple : php artisan chorabase:tunnel https://abc-def.trycloudflare.com');

            return self::FAILURE;
        }

        if (! str_starts_with($adresse, 'https://')) {
            $this->components->error("L'adresse doit commencer par https:// — c'est ce que donne cloudflared.");

            return self::FAILURE;
        }

        $hote = parse_url($adresse, PHP_URL_HOST);

        if (! $hote) {
            $this->components->error("Adresse illisible : {$adresse}");

            return self::FAILURE;
        }

        return $this->appliquer($adresse);
    }

    protected function appliquer(?string $adresse): int
    {
        $fichier = base_path('.env');

        if (! is_readable($fichier) || ! is_writable($fichier)) {
            $this->components->error('Impossible de lire ou modifier le fichier .env.');

            return self::FAILURE;
        }

        $contenu = file_get_contents($fichier);
        $hote = $adresse ? parse_url($adresse, PHP_URL_HOST) : null;

        // En mode tunnel, l'adresse publique devient la référence pour tout :
        // les liens audio (APP_URL), les liens d'invitation (FRONTEND_URL),
        // et les domaines autorisés à ouvrir une session (Sanctum).
        $valeurs = $adresse
            ? [
                'TUNNEL_URL' => $adresse,
                'APP_URL' => $adresse,
                'FRONTEND_URL' => $adresse,
                'SANCTUM_STATEFUL_DOMAINS' => $hote.',localhost:8000,127.0.0.1:8000,localhost:5173,127.0.0.1:5173',
            ]
            : [
                'TUNNEL_URL' => '',
                'APP_URL' => 'http://localhost:8000',
                'FRONTEND_URL' => 'http://localhost:5173',
                'SANCTUM_STATEFUL_DOMAINS' => 'localhost:5173,127.0.0.1:5173,localhost:8000,127.0.0.1:8000',
            ];

        foreach ($valeurs as $cle => $valeur) {
            $contenu = $this->definir($contenu, $cle, $valeur);
        }

        file_put_contents($fichier, $contenu);

        Artisan::call('config:clear');

        if ($adresse) {
            $this->components->info('Mode tunnel activé.');
            $this->newLine();
            $this->line("  Adresse à partager : <fg=yellow>{$adresse}</>");
            $this->newLine();
            $this->line('  Lance maintenant (ou laisse tourner) :  php artisan serve');
            $this->line('  Ouvre l\'adresse sur ton téléphone pour vérifier avant de la diffuser.');
        } else {
            $this->components->info('Retour en mode local.');
            $this->line('  L\'application répond de nouveau sur http://localhost:8000');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /** Remplace la ligne si la clé existe, l'ajoute sinon. */
    protected function definir(string $contenu, string $cle, string $valeur): string
    {
        $ligne = $cle.'='.$valeur;
        $motif = '/^'.preg_quote($cle, '/').'=.*$/m';

        if (preg_match($motif, $contenu)) {
            return preg_replace($motif, $ligne, $contenu, 1);
        }

        return rtrim($contenu, "\r\n")."\n".$ligne."\n";
    }
}
