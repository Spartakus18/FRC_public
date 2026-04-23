<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('sources')->insert([
            "nom_source"=>"Magasin",
        ]);
        DB::table('sources')->insert([
            "nom_source"=>"Autre (station ou client)",
        ]);
    }
}
