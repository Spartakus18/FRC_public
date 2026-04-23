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
        Schema::create('fournitures', function (Blueprint $table) {
            $table->id();
            $table->string('nom_article');
            $table->string('reference');
            $table->string('numero_serie')->nullable()->unique();
            $table->enum('etat', ['neuf', 'bon', 'moyen', 'a_verifier', 'hors_service'])->default('bon');
            $table->boolean('is_dispo')->default(true);
            $table->date('date_acquisition');
            $table->foreignId('materiel_id_associe')->nullable()->constrained('materiels')->nullOnDelete();
            $table->string('autre_materiel_nom')->nullable();
            $table->date('date_sortie_stock')->nullable();
            $table->date('date_retour_stock')->nullable();
            $table->foreignId('lieu_stockage_id')->nullable()->constrained('lieu_stockages');
            $table->string('localisation_actuelle')->default(null)->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->index('is_dispo');
            $table->index('etat');
            $table->index('localisation_actuelle');
            $table->index('materiel_id_associe');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fournitures');
    }
};
