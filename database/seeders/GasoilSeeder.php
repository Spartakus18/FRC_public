<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GasoilSeeder extends Seeder
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

        $gasoils = [];

        for ($i = 0; $i < 20; $i++) {
            // Déterminer aléatoirement si la source est une station ou un lieu de stockage
            $sourceType = $faker->boolean(70); // 70% de chance d'avoir une source station

            $gasoils[] = [
                'source_station' => $sourceType ? $faker->company : null,
                'source_lieu_stockage_id' => $sourceType ? null : $faker->numberBetween(1, 2),
                'quantite' => $faker->randomFloat(2, 50, 500),
                'prix_gasoil' => $faker->randomFloat(2, 1.5, 2.5),
                'materiel_id_cible' => $faker->numberBetween(1, 10),
                'ajouter_par' => $ajouterPar,
                'modifier_par' => $faker->optional(0.3)->name,
                'created_at' => $faker->dateTimeBetween('-2 years', 'now'),
                'updated_at' => Carbon::now(),
            ];
        }

        // Calcul du prix_total pour chaque enregistrement
        foreach ($gasoils as &$gasoil) {
            if ($gasoil['quantite'] && $gasoil['prix_gasoil']) {
                $gasoil['prix_total'] = $gasoil['quantite'] * $gasoil['prix_gasoil'];
            }
        }

        // Insertion par lots
        $chunks = array_chunk($gasoils, 100);
        foreach ($chunks as $chunk) {
            DB::table('gasoils')->insert($chunk);
        }
    }
}
