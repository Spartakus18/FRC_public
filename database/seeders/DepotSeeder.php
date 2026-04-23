<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('depots')->insert([
            'nom_depot'=>'Analamalotra',
        ]);
        DB::table('depots')->insert([
            'nom_depot'=>'Carrière',
        ]);
    }
}
