<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LieuStockageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('lieu_stockages')->insert([
            'nom'=>'Stock(zone de production)',
        ]);
        DB::table('lieu_stockages')->insert([
            'nom'=>'Magasin(stockage en ville)',
        ]);
        DB::table('lieu_stockages')->insert([
            'nom'=>'Dépôt',
        ]);
    }
}
