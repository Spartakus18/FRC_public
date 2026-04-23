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
        Schema::create('perte_gasoil_operations', function (Blueprint $table) {
            $table->id();
            $table->decimal('gasoil_avant', 10, 2)->nullable();
            $table->decimal('gasoil_apres', 10, 2)->nullable();
            $table->foreignId('gasoil_id')->nullable()->constrained('gasoils')->onDelete('cascade');
            $table->text('motif')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('materiel_id')->nullable()->constrained('materiels')->onDelete('set null');
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
        Schema::dropIfExists('perte_gasoil_operations');
    }
};
