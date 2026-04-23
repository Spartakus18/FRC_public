<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConducteurSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('conducteurs')->insert([
            'nom_conducteur'=>'Cisco',
        ]);
        DB::table('conducteurs')->insert([
            'nom_conducteur'=>'Yaly',
        ]);
        DB::table('conducteurs')->insert([
            'nom_conducteur'=>'Njara',
        ]);
        DB::table('conducteurs')->insert([
            'nom_conducteur'=>'Randria',
        ]);
        DB::table('conducteurs')->insert([
            'nom_conducteur'=>'Razaka',
        ]);
        DB::table('conducteurs')->insert([
            'nom_conducteur'=>'Kevin',
        ]);
        DB::table('conducteurs')->insert([
            'nom_conducteur'=>'Sydo',
        ]);
        DB::table('conducteurs')->insert([
            'nom_conducteur'=>'Jeannito',
        ]);
    }
}
