<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorieMaterielSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('categorie_materiels')->insert([
            'nom_categorie'=>'Groupe',
        ]);

        DB::table('categorie_materiels')->insert([
            'nom_categorie'=>'Production',
        ]);

        DB::table('categorie_materiels')->insert([
            'nom_categorie'=>'Transport',
        ]);
    }
}
