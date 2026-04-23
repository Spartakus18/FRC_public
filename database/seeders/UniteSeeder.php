<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UniteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('unites')->insert([
            "nom_unite"=>"Cm",
        ]);
        DB::table('unites')->insert([
            "nom_unite"=>"L",
        ]);
        DB::table('unites')->insert([
            "nom_unite"=>"m3",
        ]);
        DB::table('unites')->insert([
            "nom_unite"=>"Kg",
        ]);
        DB::table('unites')->insert([
            "nom_unite"=>"Pièce",
        ]);
        DB::table('unites')->insert([
            "nom_unite"=>"Fu",
        ]);
        DB::table('unites')->insert([
            "nom_unite"=>"Tonne",
        ]);
    }
}
