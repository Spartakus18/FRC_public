<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * PRODUCTION
     * @return void
     */
    public function run()
    {
        DB::table('categories')->insert([
            'nom_categorie'=>'MANGOROMBATO',
        ]);
        DB::table('categories')->insert([
            'nom_categorie'=>'LIVRAISON',
        ]);
        DB::table('categories')->insert([
            'nom_categorie'=>'CHARGEMENT',
        ]);
        DB::table('categories')->insert([
            'nom_categorie'=>'LALANA',
        ]);
        DB::table('categories')->insert([
            'nom_categorie'=>'ELECTRICITE',
        ]);
        DB::table('categories')->insert([
            'nom_categorie'=>'ENTRETIEN',
        ]);
        DB::table('categories')->insert([
            'nom_categorie'=>'BRH',
        ]);
        DB::table('categories')->insert([
            'nom_categorie'=>'DEGAGEMENT',
        ]);
        DB::table('categories')->insert([
            'nom_categorie'=>'PLATEFORME',
        ]);
        DB::table('categories')->insert([
            'nom_categorie'=>'TRANSFERT GO',
        ]);
        DB::table('categories')->insert([
            'nom_categorie'=>'LAVAGE',
        ]);
    }
}
