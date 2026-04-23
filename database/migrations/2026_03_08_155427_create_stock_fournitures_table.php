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
        Schema::create('stock_fournitures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fourniture_id')->constrained('fourniture_consommables')->onDelete('cascade');
            $table->foreignId('lieu_stockage_id')->constrained('lieu_stockages')->onDelete('cascade');
            $table->decimal('quantite', 10, 2)->default(0);
            $table->unique(['fourniture_id', 'lieu_stockage_id']);
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
        Schema::dropIfExists('stock_fournitures');
    }
};
