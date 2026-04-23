<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HuilesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = \Faker\Factory::create('fr_FR');
        $ajouterPar = 'System';

        // IDs des articles de type huile (16, 17, 18 selon ArticleDepotSeeder)
        $articlesHuile = [16, 17, 18];

        $huiles = [];

        for ($i = 0; $i < 20; $i++) {
            // Déterminer aléatoirement si la source est une station ou un lieu de stockage
            $sourceType = $faker->boolean(70); // 70% de chance d'avoir une source station

            $huiles[] = [
                'source_station' => $sourceType ? $faker->company : null,
                'source_lieu_stockage_id' => $sourceType ? null : $faker->numberBetween(1, 2),
                'quantite' => $faker->randomFloat(2, 1, 100),
                'prix_total' => $faker->optional(0.8)->randomFloat(2, 50, 500),
                'materiel_id_cible' => $faker->numberBetween(1, 10),
                'subdivision_id_cible' => $faker->numberBetween(1, 10),
                'article_versement_id' => $faker->randomElement($articlesHuile),
                'ajouter_par' => $ajouterPar,
                'modifier_par' => $faker->optional(0.3)->name,
                'created_at' => $faker->dateTimeBetween('-2 years', 'now'),
                'updated_at' => Carbon::now(),
            ];
        }

        // Insertion par lots pour optimiser les performances
        $chunks = array_chunk($huiles, 100);
        foreach ($chunks as $chunk) {
            DB::table('huiles')->insert($chunk);
        }
    }
}
