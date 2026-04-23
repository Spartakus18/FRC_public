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
        Schema::create('materiels', function (Blueprint $table) {
            $table->id();
            $table->string('nom_materiel', 100)->unique();

            $table->boolean('status')->default(false); // Pour connaître si le matériel est disponnible ou non

            $table->boolean('seuil_notif')->nullable(); // Pour la notification du seuil

            // Pour catégorisé les matériels
            $table->enum('categorie', ['groupe', 'vehicule', 'engin']);

            /*
                Section pour gasoil
            */
            // capaciter max pour cuve de gasoil en Litre
            $table->decimal('capaciteL', 8, 2)->nullable();
            // capaciter max en Cm
            $table->decimal('capaciteCm', 8, 2)->nullable();
            // seuil de sécurité du cuve de gasoil
            $table->decimal('seuil', 8, 2)->nullable();
            // pour consommation horaire
            $table->decimal('consommation_horaire', 8, 2)->nullable();
            // nombre actuel de gasoil dans le matériel
            $table->decimal('actuelGasoil', 8, 2)->nullable();
            // nombre actuel de gasoil dans le matériel
            $table->decimal('gasoil_consommation', 8, 2)->nullable();
            /*
                Section pour pneu
            */
            // pour les type vehicules
            $table->integer('nbr_pneu')->nullable();

            $table->decimal('compteur_actuel', 8, 2)->nullable();

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
        Schema::dropIfExists('vehicules');
    }
};
