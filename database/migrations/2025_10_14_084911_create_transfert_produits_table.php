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
        Schema::create('transfert_produits', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->time('heure_depart');
            $table->time('heure_arrivee')->nullable();
            $table->foreignId('materiel_id')->constrained('materiels');
            $table->decimal('gasoil_depart', 10, 2);
            $table->decimal('gasoil_arrive', 10, 2)->nullable();
            $table->decimal('compteur_depart', 10, 2)->nullable();
            $table->decimal('compteur_arrive', 10, 2)->nullable();

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

            $table->boolean('isDelivred')->default(false);
            $table->foreignId('chauffeur_id')->constrained('conducteurs');
            $table->foreignId('aideChauffeur_id')->nullable()->constrained('aide_chauffeurs');
            $table->text('remarque')->nullable();
            $table->foreignId('produit_id')->constrained('article_depots');
            $table->foreignId('lieu_stockage_depart_id')->constrained('lieu_stockages');
            $table->foreignId('lieu_stockage_arrive_id')->constrained('lieu_stockages');
            $table->decimal('quantite', 10, 2);
            $table->foreignId('unite_id')->constrained('unites');
            $table->foreignId('bon_transfert_id')->constrained('bon_transferts');
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
        Schema::dropIfExists('transfert_produits');
    }
};
