<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterielFusionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'4*4 Pick Up FORD',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'ATEGO',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'BELL T17',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'HOWO 1 5630TCB',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'CONCASSEUR',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'FOREUSE',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'GE 2 VERT',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'GE OLYMPIAN JAUNE',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'Groupe KUBOTA',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'Groupe TOTAL',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'MAN 1 TGA 2095AJ',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'MAN 2 TGA 0126TBV',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'Pelle CAT 325-D',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'Pelle HITACHI',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'TRAX 936',
        ]);
        DB::table('materiel_fusions')->insert([
            'nom_materiel'=>'TRAX 950 E',
        ]);
    }
}
