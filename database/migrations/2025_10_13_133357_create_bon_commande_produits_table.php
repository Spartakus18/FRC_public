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
        Schema::create('bon_commande_produits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bon_commande_id')
                ->constrained('bon_commandes')
                ->onDelete('cascade');
            $table->foreignId('article_id')
                ->constrained('article_depots')
                ->onDelete('cascade');
            $table->foreignId('lieu_stockage_id')
                ->constrained('lieu_stockages')
                ->onDelete('cascade');
            $table->foreignId('unite_id')
                ->nullable()
                ->constrained('unites')
                ->onDelete('set null');
            $table->decimal('quantite', 10, 2);
            $table->decimal('pu', 15, 2);
            $table->decimal('montant', 20, 2);
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
        Schema::dropIfExists('bon_commande_produits');
    }
};
