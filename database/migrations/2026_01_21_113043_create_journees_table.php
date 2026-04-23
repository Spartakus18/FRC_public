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
        Schema::create('journees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id_start')->constrained('users')->onDelete('cascade');
            $table->foreignId('user_id_end')->nullable()->constrained('users')->onDelete('cascade');
            $table->boolean('isBegin')->default(false);
            $table->boolean('isEnd')->default(false);
            $table->date('date')->unique(); // Chaque jour ne peut avoir qu'une seule journée
            $table->text('notes')->nullable(); // Notes optionnelles
            $table->timestamps();

            // Index pour améliorer les performances
            $table->index('date');
            $table->index('isBegin');
            $table->index('isEnd');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('journees');
    }
};
