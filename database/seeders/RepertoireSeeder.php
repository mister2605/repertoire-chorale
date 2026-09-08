<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\Chorale;
use App\Models\Pupitre;
use App\Support\ChoraleCourante;
use Illuminate\Database\Seeder;

class RepertoireSeeder extends Seeder
{
    public function run(): void
    {
        $chorale = Chorale::firstOrCreate(
            ['slug' => 'ndps-ouaga-2000'],
            ['nom' => 'Chorale NDPS Ouaga 2000', 'ville' => 'Ouagadougou', 'actif' => true],
        );

        // Pupitres et catégories appartiennent désormais à UNE chorale :
        // chaque nouvelle chorale aura les siens.
        app(ChoraleCourante::class)->pour($chorale->id, function () {
            foreach (['Soprano', 'Alto', 'Ténor', 'Basse'] as $nom) {
                Pupitre::firstOrCreate(['nom' => $nom]);
            }

            foreach ([
                'Avent / Carême', 'Noël / Pâques', 'Temps ordinaire', 'Pentecôte / Fêtes', 'Mariage',
                'Adoration', 'Action de grâce', 'Louange',
            ] as $nom) {
                Categorie::firstOrCreate(['nom' => $nom]);
            }
        });
    }
}
