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
        Schema::create('vidanges', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('bon');
            $table->unsignedBigInteger('materiel_id');
            $table->foreign('materiel_id')->references('id')->on('materiel_fusions')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('compteur');
            $table->unsignedBigInteger('subdivision_id');
            $table->foreign('subdivision_id')->references('id')->on('subdivisions')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('article_id');
            $table->foreign('article_id')->references('id')->on('article_depots')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('quantite');
            $table->integer('heure_vidange');
            $table->integer('compteur_vidange');
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
        Schema::dropIfExists('vidanges');
    }
};
