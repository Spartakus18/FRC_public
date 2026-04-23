<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubdivisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('subdivisions')->insert([
            "nom_subdivision"=>"Assisté direction (pompe caline)",
        ]);
        DB::table('subdivisions')->insert([
            "nom_subdivision"=>"Atelier mecanique",
        ]);
        DB::table('subdivisions')->insert([
            "nom_subdivision"=>"ATV primaire",
        ]);
        DB::table('subdivisions')->insert([
            "nom_subdivision"=>"Boite convertisseur",
        ]);
        DB::table('subdivisions')->insert([
            "nom_subdivision"=>"Crique",
        ]);
        DB::table('subdivisions')->insert([
            "nom_subdivision"=>"Moteur",
        ]);
        DB::table('subdivisions')->insert([
            "nom_subdivision"=>"Pont",
        ]);
        DB::table('subdivisions')->insert([
            "nom_subdivision"=>"Radiateur",
        ]);
        DB::table('subdivisions')->insert([
            "nom_subdivision"=>"Réservoir",
        ]);
        DB::table('subdivisions')->insert([
            "nom_subdivision"=>"Verin",
        ]);
    }
}
