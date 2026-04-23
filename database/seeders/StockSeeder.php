<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('stocks')->insert(
            [
                ['article_id' => '6', 'lieu_stockage_id' => '1', 'quantite' => 1000],
                ['article_id' => '6', 'lieu_stockage_id' => '2', 'quantite' => 2000],
                ['article_id' => '17', 'lieu_stockage_id' => '1', 'quantite' => 500],
                ['article_id' => '17', 'lieu_stockage_id' => '2', 'quantite' => 600]
            ]
        );
    }
}
