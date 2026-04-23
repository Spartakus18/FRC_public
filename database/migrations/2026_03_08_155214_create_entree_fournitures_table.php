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
        Schema::create('entree_fournitures', function (Blueprint $table) {
            $table->id();
            $table->string('user_name');
            $table->foreignId('fourniture_id')->constrained('fourniture_consommables')->onDelete('cascade');
            $table->foreignId('lieu_stockage_id')->constrained('lieu_stockages')->onDelete('cascade');
            $table->foreignId('unite_id')->constrained('unites');
            $table->decimal('quantite', 10, 2);
            $table->decimal('prix_unitaire', 10, 2)->nullable();
            $table->decimal('prix_total', 10, 2)->nullable();
            $table->string('motif')->nullable();
            $table->date('entre');
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
        Schema::dropIfExists('entree_fournitures');
    }
};
