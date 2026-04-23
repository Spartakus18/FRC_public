<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UniteFacturationSeeder extends Seeder
{
    /**
     * Run the database seeds. Heure, Jour, Poids, Volume
     *
     * @return void
     */
    public function run()
    {
        DB::table('unite_facturations')->insert([
            'nom_unite'=>'Heure',
        ]);
        DB::table('unite_facturations')->insert([
            'nom_unite'=>'Jour',
        ]);
        DB::table('unite_facturations')->insert([
            'nom_unite'=>'Poids',
        ]);
        DB::table('unite_facturations')->insert([
            'nom_unite'=>'Volume',
        ]);
    }
}
