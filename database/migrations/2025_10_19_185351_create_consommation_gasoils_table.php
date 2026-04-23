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
        Schema::create('consommation_gasoils', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('materiels')->onDelete('cascade');

            // Données horaires
            $table->decimal('consommation_reelle_par_heure', 8, 2)->nullable();
            $table->decimal('consommation_horaire_reference', 8, 2)->nullable();
            $table->decimal('ecart_consommation_horaire', 8, 2)->nullable();
            $table->string('statut_consommation_horaire', 20)->nullable();

            //Données destination
            $table->decimal('consommation_totale', 8, 2)->nullable();
            $table->decimal('consommation_destination_reference', 8, 2)->nullable();
            $table->decimal('ecart_consommation_destination', 8, 2)->nullable();
            $table->string('statut_consommation_destination', 20)->nullable();

            $table->foreignId('bon_livraison_id')->nullable()->constrained('bon_livraisons');
            $table->foreignId('transfert_produit_id')->nullable()->constrained('transfert_produits');
            $table->foreignId('production_materiel_id')->nullable()->constrained('production_materiels');
            $table->foreignId('operation_vehicule_id')->nullable()->constrained('operation_vehicules');

            $table->foreignId('destination_id')->nullable()->constrained('destinations');

            $table->decimal('quantite', 10, 2); // quantité de gasoil consommée
            $table->decimal('distance_km', 10, 2)->nullable(); // distance parcourue
            $table->date('date_consommation'); // date d’enregistrement
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
        Schema::dropIfExists('consommation_gasoils');
    }
};
