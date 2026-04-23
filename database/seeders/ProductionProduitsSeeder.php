<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductionProduitsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer tous les produits (productions)
        $productions = DB::table('produits')->where('id', '>=', 4)->get();
        $productionProduits = [];

        // Articles disponibles (basé sur votre exemple)
        $articles = [
            ['id' => 2, 'nom' => 'Blocage 100/300'],
            ['id' => 8, 'nom' => 'Gravillon 0/100'],
            ['id' => 1, 'nom' => 'Sable 0/3'],
            ['id' => 5, 'nom' => 'Gravier 20/40'],
            ['id' => 6, 'nom' => 'Pierre concassée'],
        ];

        // Lieux de stockage disponibles
        $lieuxStockage = [1, 2];

        // Unités disponibles (l'unité 6 est "Fu" selon votre exemple)
        $unites = [6, 3, 4];

        foreach ($productions as $production) {
            // 1 à 3 produits par production
            $nbProduits = rand(1, 3);

            for ($i = 0; $i < $nbProduits; $i++) {
                $article = $articles[array_rand($articles)];
                $quantite = $this->getQuantiteAleatoire($article['id']);

                $productionProduits[] = [
                    'production_id' => $production->id,
                    'produit_id' => $article['id'],
                    'lieu_stockage_id' => $lieuxStockage[array_rand($lieuxStockage)],
                    'quantite' => $quantite,
                    'unite_id' => $unites[array_rand($unites)],
                    'observation' => $this->getObservationProduit(),
                    'created_at' => $production->created_at,
                    'updated_at' => $production->updated_at,
                ];
            }
        }

        DB::table('production_produits')->insert($productionProduits);
    }

    private function getQuantiteAleatoire(int $articleId): float
    {
        // Différentes gammes de quantité selon le type d'article
        $gammes = [
            1 => [1000, 5000],    // Sable
            2 => [500, 3000],     // Blocage
            5 => [1000, 4000],    // Gravier
            6 => [800, 2500],     // Pierre
            8 => [3000, 8000],    // Gravillon
        ];

        $gamme = $gammes[$articleId] ?? [1000, 5000];
        return round(rand($gamme[0], $gamme[1]) * (rand(90, 110) / 100), 2);
    }

    private function getObservationProduit(): string
    {
        $observations = [
            'Production standard',
            'Qualité vérifiée',
            'Stockage en attente',
            'Prêt pour livraison',
            'Contrôle qualité passé',
            'Humidité normale',
            'Granulométrie conforme',
            'Pas d\'observation particulière',
            'Lot homogène',
            'Conforme aux spécifications',
        ];

        return $observations[array_rand($observations)];
    }
}
