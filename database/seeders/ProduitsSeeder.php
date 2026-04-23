<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProduitsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Données de production pour les 30 derniers jours
        $produits = [];
        $now = Carbon::now();

        for ($i = 0; $i < 30; $i++) {
            $date = $now->copy()->subDays(30 - $i); // Répartir sur 30 jours

            // 1 à 3 productions par jour
            $productionsParJour = rand(1, 3);

            for ($j = 0; $j < $productionsParJour; $j++) {
                $heureDebut = rand(8, 15); // Entre 8h et 15h
                $heureFin = $heureDebut + rand(2, 8); // Durée de 2 à 8 heures

                $produits[] = [
                    'id' => count($produits) + 4, // Commencer à 4
                    'date_prod' => $date->format('Y-m-d'),
                    'isProduct' => 0,
                    'heure_debut' => sprintf('%02d:%02d:00', $heureDebut, rand(0, 59)),
                    'heure_fin' => sprintf('%02d:%02d:00', $heureFin, rand(0, 59)),
                    'remarque' => $this->getRemarqueAleatoire(),
                    'create_user_id' => 1,
                    'update_user_id' => 1,
                    'created_at' => $date->format('Y-m-d H:i:s'),
                    'updated_at' => $date->format('Y-m-d H:i:s'),
                ];
            }
        }

        DB::table('produits')->insert($produits);
    }

    private function getRemarqueAleatoire(): string
    {
        $remarques = [
            'Production normale',
            'Test de production',
            'Production urgente',
            'Maintenance préventive',
            'Production avec matériel neuf',
            'Production en condition difficile',
            'Production avec arrêt technique',
            'Production avec problème de qualité',
            'Production avec équipe réduite',
            'Production avec nouveau matériel',
        ];

        return $remarques[array_rand($remarques)];
    }
}
