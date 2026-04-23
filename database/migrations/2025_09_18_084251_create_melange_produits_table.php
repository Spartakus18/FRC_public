<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('melange_produits', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('produit_a_id')->constrained('article_depots');
            $table->foreignId('produit_b_id')->constrained('article_depots');
            $table->foreignId('lieu_stockage_a_id')->constrained('lieu_stockages');
            $table->foreignId('lieu_stockage_b_id')->constrained('lieu_stockages');
            $table->foreignId('lieu_stockage_final_id')->constrained('lieu_stockages');
            $table->decimal('quantite_a', 10, 2);
            $table->decimal('quantite_b_consommee', 10, 2);
            $table->foreignId('unite_livraison_id')->constrained('unites');
            $table->decimal('quantite_b_produite', 10, 2);
            $table->text('observation')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('melange_produits');
    }
};
