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
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->date('date_prod');
            $table->boolean('isProduct')->default(false);
            $table->time('heure_debut');
            $table->time('heure_fin')->nullable();
            $table->text('remarque')->nullable();

            // Pour suivre l'action utilisateur sur le produit
            $table->unsignedBigInteger('create_user_id')->nullable();
            $table->foreign('create_user_id')->references('id')->on('users')->onUpdate('cascade');
            $table->unsignedBigInteger('update_user_id')->nullable();
            $table->foreign('update_user_id')->references('id')->on('users')->onUpdate('cascade');

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
        Schema::dropIfExists('produits');
    }
};
