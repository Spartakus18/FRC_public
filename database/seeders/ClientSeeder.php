<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // DB::table('clients')->insert([
        //     'nom_client'=>'Archidiocese Catholique',
        // ]);
        DB::table('clients')->insert([
            'nom_client'=>'Clients divers',
        ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'E/se Herimanana',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'E/se Quickbat',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'ERRA',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'Masera Ambodimozinga',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'Mopera',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'Mr Elise',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'Mr Kenny',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'Mr Longo',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'ONG Saint Gabriel',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'Salone',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'SCORF',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'SEMT',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'SMATP',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'SMCC',
        // ]);
        // DB::table('clients')->insert([
        //     'nom_client'=>'SMCM',
        // ]);
    }
}
