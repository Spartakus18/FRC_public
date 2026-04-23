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
        Schema::create('bon_huiles', function (Blueprint $table) {
            $table->id();
            $table->string("num_bon");

            $table->foreignId('source_lieu_stockage_id')->nullable()
                ->constrained('lieu_stockages')
                ->onUpdate('cascade');

            // Audit
            $table->string('ajouter_par', 100);
            $table->string('modifier_par', 100)->nullable();

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
        Schema::dropIfExists('bon_huiles');
    }
};
