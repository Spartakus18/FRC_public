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
        Schema::create('gasoils', function (Blueprint $table) {
            $table->id();

            // Bon gasoil
            $table->foreignId('bon_id', 100)->nullable()
                ->constrained('bon_gasoils')
                ->onUpdate('cascade')
                ->onDelete('set null');

            // Type d’opération
            $table->enum('type_operation', ['versement', 'transfert']);

            // null si source station
            $table->foreignId('source_lieu_stockage_id')->nullable()
                ->constrained('lieu_stockages')
                ->onUpdate('cascade');

            // null si source lieu_stockage
            $table->string('source_station', 50)->nullable();

            // Données financières & quantitatives
            $table->decimal('quantite', 10, 2)->nullable(); // litres
            // pour la variation des stocks
            $table->decimal('quantite_stock_avant', 10, 2)->nullable();
            $table->decimal('quantite_stock_apres', 10, 2)->nullable();

            $table->decimal('prix_gasoil', 12, 2)->nullable(); // prix unitaire
            $table->decimal('prix_total', 14, 2)->nullable(); // prix global

            $table->decimal('materiel_go_avant', 10, 2)->nullable(); // pour suivis de la journee 
            $table->decimal('materiel_go_apres', 10, 2)->nullable(); // pour suivis de la journee

            // Relations avec véhicules ( modifier par materiel)
            // Le véhicule sur lequel on prendra du gasoil pour transfert
            $table->foreignId('materiel_id_source')
                ->nullable()
                ->constrained('materiels')
                ->onDelete('set null');

            // Le véhicule (modifier par materiel) sur lequel on injectera le gasoil
            $table->foreignId('materiel_id_cible') // table obligatoire
                ->nullable()
                ->constrained('materiels')
                ->onDelete('cascade');

            $table->boolean("is_consumed")->default(false);
            // Audit
            $table->string('ajouter_par', 100);
            $table->string('modifier_par', 100)->nullable();

            // Horodatage
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gasoils');
    }
};
