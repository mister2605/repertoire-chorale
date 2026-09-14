<?php

namespace App\Console\Commands;

use App\Models\Pupitre;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Crée (ou remet à zéro) un compte de test, immédiatement utilisable.
 *
 *   php artisan chorabase:compte-test choriste
 *   php artisan chorabase:compte-test instrumentiste --pupitre=Basse
 *   php artisan chorabase:compte-test choriste --email=alto@test.local --mot-de-passe=monmotdepasse
 *
 * Pourquoi une commande plutôt que l'écran Membres : pour vérifier ce que voit
 * un choriste, il faut pouvoir créer un compte, s'y connecter, puis revenir au
 * sien — plusieurs fois de suite. Passer par un lien d'invitation à chaque
 * essai décourage de faire le test, et un test qu'on ne fait pas ne sert à rien.
 *
 * Garde-fou : refuse de s'exécuter en production. Un compte au mot de passe
 * connu n'a rien à faire sur un serveur que la chorale utilise vraiment.
 */
class CompteTest extends Command
{
    protected $signature = 'chorabase:compte-test
                            {role=choriste : choriste, instrumentiste ou maitre_choeur}
                            {--email= : adresse du compte (par défaut <role>@test.local)}
                            {--mot-de-passe= : mot de passe (par défaut motdepasse)}
                            {--pupitre= : nom du pupitre à rattacher (Soprano, Alto...)}';

    protected $description = 'Crée un compte de test utilisable tout de suite (jamais en production)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->components->error(
                'Refusé en production. Un compte au mot de passe connu ne doit pas exister '
                .'sur le serveur que la chorale utilise.'
            );

            return self::FAILURE;
        }

        $role = (string) $this->argument('role');

        if (! in_array($role, User::ROLES, true)) {
            $this->components->error("Rôle inconnu : {$role}");
            $this->line('  Rôles possibles : '.implode(', ', User::ROLES));

            return self::FAILURE;
        }

        // La chorale de référence est celle du maître de chœur existant :
        // un compte de test rattaché à une autre chorale ne verrait rien,
        // et on chercherait longtemps pourquoi.
        $reference = User::where('role', 'maitre_choeur')->whereNotNull('chorale_id')->first();

        if (! $reference) {
            $this->components->error('Aucun maître de chœur en base : impossible de savoir à quelle chorale rattacher le compte.');

            return self::FAILURE;
        }

        $email = (string) ($this->option('email') ?: str_replace('_', '-', $role).'@test.local');
        $motDePasse = (string) ($this->option('mot-de-passe') ?: 'motdepasse');

        $pupitreId = null;
        if ($nomPupitre = $this->option('pupitre')) {
            // En console, le filtre automatique de chorale ne s'applique pas :
            // on filtre donc explicitement, sinon on rattacherait le compte au
            // pupitre d'une autre chorale.
            $pupitre = Pupitre::where('chorale_id', $reference->chorale_id)
                ->where('nom', $nomPupitre)
                ->first();

            if (! $pupitre) {
                $this->components->error("Pupitre introuvable dans cette chorale : {$nomPupitre}");
                $this->line('  Disponibles : '.Pupitre::where('chorale_id', $reference->chorale_id)->pluck('nom')->implode(', '));

                return self::FAILURE;
            }

            $pupitreId = $pupitre->id;
        }

        $existant = User::where('email', $email)->first();

        $compte = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $existant?->name ?? 'Test '.ucfirst($role),
                'password' => Hash::make($motDePasse),
                'role' => $role,
                'pupitre_id' => $pupitreId ?? $existant?->pupitre_id,
                'chorale_id' => $reference->chorale_id,
                // Sans cette ligne le compte existe mais refuse la connexion :
                // il est considéré comme "en attente d'invitation".
                'active_le' => now(),
            ]
        );

        $this->newLine();
        $this->components->info($existant ? 'Compte de test remis à zéro.' : 'Compte de test créé.');
        $this->newLine();
        $this->line("  Email        : <fg=yellow>{$compte->email}</>");
        $this->line("  Mot de passe : <fg=yellow>{$motDePasse}</>");
        $this->line("  Rôle         : {$compte->role}");
        $this->line('  Pupitre      : '.($compte->pupitre?->nom ?? '—'));
        $this->line("  Chorale      : {$reference->chorale?->nom}");
        $this->newLine();
        $this->line('  Ouvre l\'appli dans une fenêtre de navigation privée pour rester');
        $this->line('  connecté en maître de chœur dans l\'autre fenêtre.');
        $this->newLine();

        return self::SUCCESS;
    }
}
