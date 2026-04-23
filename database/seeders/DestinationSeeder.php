<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DestinationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('destinations')->insert([
            'nom_destination' => 'AMBALATAVOANGY',
            'consommation_reference' => 40,
        ]);
        DB::table('destinations')->insert([
            'nom_destination' => 'AMBODISAINA',
            'consommation_reference' => 50,
        ]);
        DB::table('destinations')->insert([
            'nom_destination' => 'ANTANAMBAO KELY',
            'consommation_reference' => 25,
        ]);
        DB::table('destinations')->insert([
            'nom_destination' => 'BETAINOMBY',
            'consommation_reference' => 45,
        ]);
        DB::table('destinations')->insert([
            'nom_destination' => 'CARRIERE',
        ]);
        DB::table('destinations')->insert([
            'nom_destination' => 'GARE MANGUIER',
            'consommation_reference' => 40,
        ]);
        DB::table('destinations')->insert([
            'nom_destination' => 'PONT SATAVA',
            'consommation_reference' => 12,
        ]);
        DB::table('destinations')->insert([
            'nom_destination' => 'SANDAHATRA',
            'consommation_reference' => 5,
        ]);
        DB::table('destinations')->insert([
            'nom_destination' => 'VALPINSON',
            'consommation_reference' => 33,
        ]);
        DB::table('destinations')->insert([
            'nom_destination' => 'JOVENA',
            'consommation_reference' => 30,
        ]);
        DB::table('destinations')->insert([
            'nom_destination' => 'TOTAL',
            'consommation_reference' => 23,
        ]);
        DB::table('destinations')->insert([
            'nom_destination' => 'GALANA',
            'consommation_reference' => 40,
        ]);
        DB::table('destinations')->insert([
            'nom_destination' => 'VOHILAVA',
            'consommation_reference' => 15,
        ]);
    }
}
