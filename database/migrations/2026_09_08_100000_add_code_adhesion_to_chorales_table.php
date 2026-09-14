<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lien d'adhésion : un lien unique par chorale que le maître de chœur
 * diffuse une seule fois dans le groupe WhatsApp. Chaque choriste crée
 * lui-même son compte — plus besoin de saisir 40 personnes à la main.
 *
 * Le code est un secret : il se régénère (ce qui invalide l'ancien lien)
 * et l'adhésion peut être fermée une fois tout le monde inscrit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chorales', function (Blueprint $table) {
            $table->string('code_adhesion', 32)->nullable()->unique();
            $table->boolean('adhesion_ouverte')->default(false);
        });

        // Les chorales existantes reçoivent un code, adhésion fermée par défaut :
        // on n'ouvre jamais une porte sans que quelqu'un l'ait décidé.
        foreach (DB::table('chorales')->select('id')->get() as $chorale) {
            DB::table('chorales')
                ->where('id', $chorale->id)
                ->update(['code_adhesion' => Str::random(24)]);
        }
    }

    public function down(): void
    {
        Schema::table('chorales', function (Blueprint $table) {
            $table->dropUnique(['code_adhesion']);
            $table->dropColumn(['code_adhesion', 'adhesion_ouverte']);
        });
    }
};
