<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AideChauffeurSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('aide_chauffeurs')->insert([
            'nom_aideChauffeur'=>'Andry',
        ]);
        DB::table('aide_chauffeurs')->insert([
            'nom_aideChauffeur'=>'Vélin',
        ]);
        DB::table('aide_chauffeurs')->insert([
            'nom_aideChauffeur'=>'Delphin',
        ]);
    }
}
