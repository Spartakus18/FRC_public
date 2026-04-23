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
        Schema::create('huiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bon_id', 100)->nullable()
                ->constrained('bon_huiles')
                ->onUpdate('cascade')
                ->onDelete('set null');

            // null si source lieu_stockage
            $table->string('source_station', 50)->nullable();

            $table->foreignId('source_lieu_stockage_id')->nullable()
                ->constrained('lieu_stockages')
                ->onUpdate('cascade');

            // Quantité en litres
            $table->decimal('quantite', 10, 2)->nullable();
            // pour le stock
            $table->decimal('quantite_stock_avant', 10, 2)->nullable();
            $table->decimal('quantite_stock_apres', 10, 2)->nullable();

            // Prix global (optionnel, ex: si on veut ajouter une info financière)
            $table->decimal('prix_total', 14, 2)->nullable();

            // materiel ciblé sur lequel on versera l'huile
            $table->foreignId('materiel_id_cible')
                ->nullable()
                ->constrained('materiels')
                ->onDelete('cascade');

            $table->foreignId('subdivision_id_cible')
                ->constrained('subdivisions')

                ->onDelete('cascade');

            // Type d’huile
            $table->foreignId('article_versement_id')
                ->constrained('article_depots')
                ->onDelete('restrict'); // Un type d’huile doit exister

            // transfert
            $table->enum('type_operation', ['versement', 'transfert']);

            $table->foreignId('materiel_id_source')
                ->nullable()
                ->constrained('materiels')
                ->onDelete('cascade');

            $table->foreignId('subdivision_id_source')
                ->nullable()
                ->constrained('subdivisions')
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
        Schema::dropIfExists('huiles');
    }
};
