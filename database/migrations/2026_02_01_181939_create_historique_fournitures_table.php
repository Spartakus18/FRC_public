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
        Schema::create('historique_fournitures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fourniture_id')
                  ->constrained('fournitures')
                  ->onDelete('cascade');

            $table->foreignId('ancien_materiel_id')
                  ->nullable()
                  ->constrained('materiels')
                  ->onDelete('set null');

            $table->string('ancien_materiel_nom')->nullable();

            $table->foreignId('nouveau_materiel_id')
                  ->nullable()
                  ->constrained('materiels')
                  ->onDelete('set null');

            $table->string('nouveau_materiel_nom')->nullable();

            $table->string('type_action'); // création, affectation, transfert, retrait, etc.
            $table->dateTime('date_action');
            $table->text('commentaire')->nullable();
            $table->string('etat'); // État de la fourniture au moment de l'action

            $table->timestamps();

            // Index pour les recherches fréquentes
            $table->index(['fourniture_id', 'date_action']);
            $table->index('type_action');
        });
    }

    public function down()
    {
        Schema::dropIfExists('historique_fournitures');
    }
};
