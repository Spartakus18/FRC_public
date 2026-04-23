<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorieArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('categorie_articles')->insert([
            'nom_categorie'=>'gasoil',
        ]);

        DB::table('categorie_articles')->insert([
            'nom_categorie'=>'huile',
        ]);

        DB::table('categorie_articles')->insert([
            'nom_categorie'=>'production',
        ]);
    }
}
