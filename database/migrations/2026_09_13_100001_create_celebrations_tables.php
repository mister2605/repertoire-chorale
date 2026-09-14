<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le programme des célébrations.
 *
 * Une célébration = un rendez-vous de la chorale (messe, répétition, mariage,
 * funérailles, concert) et la liste ordonnée de ce qu'on y chante.
 *
 * Deux choix de conception à ne pas perdre de vue :
 *
 *  1. Un élément du programme peut désigner un chant du répertoire OU se
 *     contenter d'un titre écrit à la main. Sans cette porte de sortie, le
 *     maître de chœur bloqué un samedi soir à 22h sur un chant non saisi
 *     retourne sur WhatsApp — et ne revient pas.
 *
 *  2. Une célébration reste un brouillon tant qu'elle n'est pas publiée.
 *     On prépare rarement un programme d'un seul jet : sans ça, les choristes
 *     voient les hésitations en direct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('celebrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chorale_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('messe');
            // Ex : "22e dimanche du temps ordinaire", "Mariage Ouédraogo".
            $table->string('titre')->nullable();
            $table->dateTime('debut_le');
            $table->string('lieu')->nullable();
            $table->text('notes')->nullable();
            // null = brouillon, visible du seul maître de chœur.
            $table->dateTime('publiee_le')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // La liste se lit toujours « les célébrations de MA chorale, par date ».
            $table->index(['chorale_id', 'debut_le']);
        });

        Schema::create('celebration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chorale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('celebration_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            // Le moment liturgique : "Entrée", "Kyrie", "Offertoire"...
            // Texte libre : chaque paroisse a ses habitudes, et une liste figée
            // dans la base obligerait à une migration pour en ajouter un.
            $table->string('moment');
            // Un chant du répertoire...
            $table->foreignId('chant_id')->nullable()->constrained()->nullOnDelete();
            // ... ou, à défaut, un titre écrit à la main.
            $table->string('titre_libre')->nullable();
            // Consignes du maître de chœur (qui commence, quel couplet...).
            $table->text('notes')->nullable();
            // Réservé aux instrumentistes : tonalité jouée, intro, tempo.
            $table->text('note_instrument')->nullable();
            $table->timestamps();

            $table->index(['celebration_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('celebration_items');
        Schema::dropIfExists('celebrations');
    }
};
