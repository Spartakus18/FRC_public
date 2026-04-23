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
        Schema::create('production_produits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_id')->nullable();
            $table->foreign('production_id')->references('id')->on('produits')->onDelete('cascade');
            $table->unsignedBigInteger('produit_id')->nullable();
            $table->foreign('produit_id')->references('id')->on('article_depots')->onDelete('cascade');
            $table->unsignedBigInteger('lieu_stockage_id')->nullable();
            $table->foreign('lieu_stockage_id')->references('id')->on('lieu_stockages')->onDelete('cascade');
            $table->decimal('quantite', 10, 2)->nullable();
            $table->foreignId('unite_id')->constrained('unites');
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
        Schema::dropIfExists('production_produits');
    }
};
