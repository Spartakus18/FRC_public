<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArticleDepotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('article_depots')->insert([
            'nom_article' => 'Blocage',
            'categorie_id' => 3,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Blocage 100/300',
            'categorie_id' => 3,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Blocage 200/400',
            'categorie_id' => 3,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Eau de refroidissement',
            'categorie_id' => 2,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Enrochement',
            'categorie_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Gasoil',
            'categorie_id' => 1,
            'unite_production_id' => 2,
            'unite_livraison_id' => 2
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Graisse',
            'categorie_id' => 2,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Gravillon 0/100',
            'categorie_id' => 3,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Gravillon 0/15',
            'categorie_id' => 3,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Gravillon 0/24',
            'categorie_id' => 3,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Gravillon 0/31.5',
            'categorie_id' => 3,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Gravillon 0/5 sable',
            'categorie_id' => 3,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Gravillon 15/25',
            'categorie_id' => 3,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Gravillon 4/7',
            'categorie_id' => 3,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Gravillon 5/15',
            'categorie_id' => 3,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Gravillon 6/10',
            'categorie_id' => 3,
            'unite_production_id' => 3,
            'unite_livraison_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Huile 90',
            'categorie_id' => 2,
            'unite_production_id' => 2,
            'unite_livraison_id' => 2
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Huile hydraulique ISO 46',
            'categorie_id' => 2,
            'unite_production_id' => 2,
            'unite_livraison_id' => 2
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'huile mauteur 15W40',
            'categorie_id' => 2,
            'unite_production_id' => 2,
            'unite_livraison_id' => 2
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Mignonette 3/8[sauterelle]',
            'categorie_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'Moellons',
            'categorie_id' => 3
        ]);
        DB::table('article_depots')->insert([
            'nom_article' => 'stérile',
            'categorie_id' => 3
        ]);
    }
}
