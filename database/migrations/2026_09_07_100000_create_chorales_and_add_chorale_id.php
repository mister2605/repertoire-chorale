<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passage mono-chorale -> multi-chorale.
 *
 * Les données existantes sont rattachées à une première chorale créée ici,
 * donc rien n'est perdu et l'application continue de fonctionner à l'identique.
 */
return new class extends Migration
{
    /** Les tables métier qui appartiennent à une chorale. */
    protected array $tables = ['users', 'chants', 'pupitres', 'categories', 'chant_versions'];

    public function up(): void
    {
        Schema::create('chorales', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('slug')->unique();
            $table->string('ville')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Chorale à laquelle on rattache toutes les données déjà en base.
        // Adapte ces trois valeurs avant de lancer la migration.
        $choraleId = DB::table('chorales')->insertGetId([
            'nom' => 'Chorale NDPS Ouaga 2000',
            'slug' => 'ndps-ouaga-2000',
            'ville' => 'Ouagadougou',
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. colonne nullable  2. on rattache l'existant  3. on rend obligatoire
        foreach ($this->tables as $nomTable) {
            Schema::table($nomTable, function (Blueprint $table) {
                $table->foreignId('chorale_id')->nullable()->constrained()->cascadeOnDelete();
            });

            DB::table($nomTable)->update(['chorale_id' => $choraleId]);

            Schema::table($nomTable, function (Blueprint $table) {
                $table->unsignedBigInteger('chorale_id')->nullable(false)->change();
            });

            Schema::table($nomTable, function (Blueprint $table) use ($nomTable) {
                $table->index('chorale_id', "{$nomTable}_chorale_id_idx");
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $nomTable) {
            Schema::table($nomTable, function (Blueprint $table) use ($nomTable) {
                $table->dropIndex("{$nomTable}_chorale_id_idx");
                $table->dropConstrainedForeignId('chorale_id');
            });
        }

        Schema::dropIfExists('chorales');
    }
};
