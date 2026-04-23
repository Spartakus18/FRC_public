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
        Schema::create('production_materiels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_id')->nullable();
            $table->foreign('production_id')->references('id')->on('produits')->onDelete('cascade');
            $table->foreignId('materiel_id')->constrained('materiels');
            $table->foreignId('categorie_travail_id')->constrained('categories');
            $table->time('heure_debut');
            $table->time('heure_fin')->nullable();
            $table->decimal('compteur_debut', 10, 2)->nullable();
            $table->decimal('compteur_fin', 10, 2)->nullable();
            $table->decimal('gasoil_debut', 10, 2);
            $table->decimal('gasoil_fin', 10, 2)->nullable();

            // Données consommation horaire
            $table->decimal('consommation_reelle_par_heure', 8, 2)->nullable();
            $table->decimal('consommation_horaire_reference', 8, 2)->nullable();
            $table->decimal('ecart_consommation_horaire', 8, 2)->nullable();
            $table->string('statut_consommation_horaire', 20)->nullable();

            // Données consommation destination
            $table->decimal('consommation_totale', 8, 2)->nullable();
            $table->decimal('consommation_destination_reference', 8, 2)->nullable();
            $table->decimal('ecart_consommation_destination', 8, 2)->nullable();
            $table->string('statut_consommation_destination', 20)->nullable();

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
        Schema::dropIfExists('production_materiels');
    }
};
