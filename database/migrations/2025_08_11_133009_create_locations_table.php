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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->timestamp('date');
            $table->unsignedBigInteger('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->text('observation');
            $table->unsignedBigInteger('materiel_id');
            $table->foreign('materiel_id')->references('id')->on('materiels')->onDelete('cascade');
            $table->unsignedBigInteger('article_id');
            $table->foreign('article_id')->references('id')->on('article_depots')->onDelete('cascade');
            $table->integer('gasoil_quantite');
            $table->integer('gasoil_avant');
            $table->integer('jauge_debut');
            $table->integer('jauge_fin');
            $table->time('heures_debut');
            $table->time('heures_fin');
            $table->integer('compteur_debut');
            $table->integer('compteur_fin');
            $table->unsignedBigInteger('conducteur_id');
            $table->foreign('conducteur_id')->references('id')->on('conducteurs')->onDelete('cascade');
            $table->unsignedBigInteger('facturation_unite_Id');
            $table->foreign('facturation_unite_Id')->references('id')->on('unite_facturations')->onDelete('cascade');
            $table->integer('facturation_quantite');
            $table->bigInteger('facturation_prixU');
            $table->bigInteger('facturation_prixT');
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
        Schema::dropIfExists('locations');
    }
};
