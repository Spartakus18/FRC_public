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
        Schema::create('perte_gasoils', function (Blueprint $table) {
            $table->id();

            $table->foreignId('materiel_id')
                ->constrained('materiels')
                ->onDelete('cascade');

            /* Perte le matin */
            $table->decimal('quantite_precedente', 10, 2)->nullable();
            $table->decimal('quantite_actuelle', 10, 2)->nullable();
            $table->decimal('quantite_perdue', 10, 2)->nullable();
            $table->string('raison_perte', 255)->nullable();

            /* Perte le soir */
            $table->decimal('quantite_precedente_soir', 10, 2)->nullable();
            $table->decimal('quantite_actuelle_soir', 10, 2)->nullable();
            $table->decimal('quantite_perdue_soir', 10, 2)->nullable();
            $table->string('raison_perte_soir', 255)->nullable();

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
        Schema::dropIfExists('perte_gasoils');
    }
};
