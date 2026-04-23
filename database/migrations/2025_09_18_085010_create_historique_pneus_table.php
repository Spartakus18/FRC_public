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
        Schema::create('historique_pneus', function (Blueprint $table) {
            $table->id();

            // Référence au pneu concerné
            $table->foreignId('pneu_id')->constrained('pneus')->onDelete('cascade');

            // Référence à l'ancien matériel
            $table->unsignedBigInteger('ancien_materiel_id')->nullable();
            $table->string('ancien_materiel_nom')->nullable();

            // État du pneu
            $table->enum('etat', ['bonne', 'usée', 'endommagée', 'défectueuse'])->nullable();

            // Référence au nouveau matériel
            $table->unsignedBigInteger('nouveau_materiel_id')->nullable();
            $table->string('nouveau_materiel_nom')->nullable();

            // Type d’action : ajout, transfert, retrait
            $table->enum('type_action', ['ajout', 'transfert', 'retrait', 'mise_hors_service', 'reparation']);

            // Date de l’action
            $table->date('date_action');

            // Notes supplémentaires
            $table->text('commentaire')->nullable();

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
        Schema::dropIfExists('historique_pneus');
    }
};
