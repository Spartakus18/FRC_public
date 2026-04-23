<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\AjustementStock\Lieu_stockage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // Désactiver les contraintes de clé étrangère temporairement
        /* \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;'); */


        $this->call([
            CategorieSeeder::class,
            lieuStockageSeeder::class,
            RoleUserSeeder::class,
            UniteSeeder::class,
            DepotSeeder::class,
            CategorieArticleSeeder::class,
            CategorieMaterielSeeder::class,
            ClientSeeder::class,
            ArticleDepotSeeder::class,
            MaterielSeeder::class,
            UniteFacturationSeeder::class,
            ConducteurSeeder::class,
            // GroupeSeeder::class,
            MaterielFusionSeeder::class,
            SourceSeeder::class,
            SubdivisionSeeder::class,
            AideChauffeurSeeder::class,
            PneuSeeder::class,
            StockSeeder::class,
            UserSeeder::class,
            DestinationSeeder::class,
            /* HuilesTableSeeder::class, */
            /* HistoriquePneuSeeder::class, */
            /* GasoilSeeder::class */
            ProduitsSeeder::class,
            ProductionMaterielsSeeder::class,
            ProductionProduitsSeeder::class,
            ConsommationGasoilsSeeder::class,
            FournitureSeeder::class,
        ]);
    }

    /* Ordre d'appel pour les seeder de rapport
    ProduitsSeeder::class,
    ProductionMaterielsSeeder::class,
    ProductionProduitsSeeder::class,
    ConsommationGasoilsSeeder::class,

    commade laravel
    php artisan db:seed --class=ProduitsSeeder
    php artisan db:seed --class=ProductionMaterielsSeeder
    php artisan db:seed --class=ProductionProduitsSeeder
    php artisan db:seed --class=ConsommationGasoilsSeeder
    */
    private function cleanTables(): void
    {
        $tables = [
            'consommation_gasoils',
            'production_produits',
            'production_materiels',
            'produits',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
            $this->command->info("Nettoyé : {$table}");
        }
    }
}
