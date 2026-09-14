<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La colonne "role" passe d'un ENUM à du texte.
 *
 * Pourquoi maintenant : on ajoute le rôle "instrumentiste", et un ENUM oblige
 * à modifier la structure de la table à chaque nouveau rôle. Le prochain
 * ("chef de pupitre") est déjà au programme.
 *
 * Pourquoi surtout : ENUM ne s'écrit pas pareil sur MySQL et sur PostgreSQL.
 * La production est prévue sur PostgreSQL — autant enlever le piège avant
 * d'avoir des données dedans plutôt qu'au moment de la migration.
 *
 * Les valeurs autorisées sont désormais vérifiées par l'application
 * (User::ROLES), c'est-à-dire à un seul endroit, lisible, et testable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('choriste')->change();
        });
    }

    public function down(): void
    {
        // L'ENUM d'origine ne connaît pas "instrumentiste" : ces comptes
        // redeviennent choristes, sinon la conversion échoue sur ces lignes.
        DB::table('users')->whereNotIn('role', ['maitre_choeur', 'choriste'])->update(['role' => 'choriste']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['maitre_choeur', 'choriste'])->default('choriste')->change();
        });
    }
};
