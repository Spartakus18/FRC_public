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
        Schema::create('article_depots', function (Blueprint $table) {
            $table->id();
            $table->string('nom_article')->unique();

            // Renommer l'unité existante comme "unité de production"
            $table->foreignId('unite_production_id')->nullable()
                ->constrained('unites')
                ->onUpdate('cascade');

            // Ajouter une nouvelle unité pour la livraison
            $table->foreignId('unite_livraison_id')->nullable()
                ->constrained('unites')
                ->onUpdate('cascade');

            // Relation avec categorie article
            $table->foreignId('categorie_id')->nullable()
                ->constrained('categorie_articles')
                ->onUpdate('cascade');

            $table->string('designation')->unique()->nullable();
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
        Schema::dropIfExists('article_depots');
    }
};
