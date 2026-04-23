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
        Schema::create('gasoil_journees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('journee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('materiel_id')->constrained()->cascadeOnDelete();
            /* $table->foreignId('gasoil_id')->nullable()->constrained()->cascadeOnDelete(); */

            $table->boolean('has_gasoil')->default(false);

            $table->decimal('gasoil_matin', 10, 2);
            $table->decimal('gasoil_soir', 10, 2)->nullable();
            $table->decimal('consommation', 10, 2)->nullable();

            $table->timestamps();

            $table->unique(['journee_id', 'materiel_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gasoil_journees');
    }
};
