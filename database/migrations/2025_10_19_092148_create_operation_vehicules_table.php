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
        Schema::create('operation_vehicules', function (Blueprint $table) {
            $table->id();
            $table->time('heure_depart');
            $table->time('heure_arrive')->nullable();
            $table->foreignId('vehicule_id')->constrained('materiels')->onDelete('cascade');
            $table->decimal('gasoil_depart', 10, 2);
            $table->decimal('gasoil_arrive', 10, 2)->nullable();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('compteur_depart', 10, 2)->nullable();
            $table->decimal('compteur_arrive', 10, 2)->nullable();
            $table->string('nbr_voyage')->nullable();
            $table->decimal('heure_machine', 10, 2)->nullable();
            $table->foreignId('chauffeur_id')->constrained('conducteurs')->onDelete('cascade');
            $table->decimal('heure_chauffeur', 10, 2)->nullable();
            $table->foreignId('aide_chauffeur_id')->nullable()->constrained('aide_chauffeurs')->onDelete('cascade');
            $table->date('date_livraison');
            $table->date('date_arriver')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('categorie_travail_id')->nullable()->constrained('categories');

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
        Schema::dropIfExists('operation_vehicules');
    }
};
