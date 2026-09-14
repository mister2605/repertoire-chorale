<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Date d'activation d'un compte.
 *
 * Un compte créé à la main par le maître de chœur reçoit un mot de passe
 * aléatoire que personne ne connaît : il est inutilisable tant que la
 * personne n'a pas ouvert son lien d'invitation. Sans cette colonne, rien
 * ne distingue à l'écran un compte prêt d'un compte en attente — et la
 * personne se heurte à "Email ou mot de passe incorrect" sans comprendre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('active_le')->nullable();
        });

        // Les comptes déjà existants ont forcément un mot de passe connu
        // de leur propriétaire (ils ont été créés avant ce mécanisme).
        DB::table('users')->whereNull('active_le')->update(['active_le' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active_le');
        });
    }
};
