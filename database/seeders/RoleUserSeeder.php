<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('role_users')->insert([
            "nom_role"=>"Administrateur",
        ]);
        DB::table('role_users')->insert([
            "nom_role"=>"Utilisateur simple",
        ]);
        DB::table('role_users')->insert([
            "nom_role"=>"Logistique",
        ]);
        DB::table('role_users')->insert([
            "nom_role"=>"Commercial",
        ]);
    }
}
