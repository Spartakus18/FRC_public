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
        Schema::create('pneus', function (Blueprint $table) {
            $table->id();

            /* les differrent Date */
            $table->date('date_obtention');
            $table->date('date_mise_en_service')->nullable();
            $table->date('date_mise_hors_service')->nullable();

            /* Etat et situation */
            $table->enum('etat', ['bonne', 'usée', 'endommagée', 'défectueuse'])->default('bonne');
            $table->enum('situation', ['en_service', 'en_stock', 'en_reparation', 'hors_service'])->default('en_service');

            /* Son emplacement dans les stocks */
            $table->unsignedBigInteger('lieu_stockages_id')->nullable();
            $table->foreign('lieu_stockages_id')
                ->references('id')
                ->on('lieu_stockages')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            /* Caractéristique */
            $table->string('caracteristiques', 100);
            $table->string('marque', 50);
            $table->string('num_serie', 50)->unique();
            $table->string('type', 50);
            $table->string('emplacement', 50)->nullable();
            $table->integer('kilometrage')->default(0);

            $table->text('observations')->nullable();

            /* Relation avec le materiel */
            $table->unsignedBigInteger('materiel_id')->nullable();
            $table->foreign('materiel_id')
                ->references('id')
                ->on('materiels')
                ->onDelete('set null');
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
        Schema::dropIfExists('pneus');
    }
};
