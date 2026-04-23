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
        Schema::create('bon_transferts', function (Blueprint $table) {
            $table->id();
            $table->string('numero_bon')->unique();
            $table->date('date_transfert');
            $table->foreignId('produit_id')->constrained('article_depots');
            $table->decimal('quantite', 10, 2);
            $table->foreignId('unite_id')->constrained('unites');
            $table->foreignId('lieu_stockage_depart_id')->constrained('lieu_stockages')->onDelete('cascade');
            $table->foreignId('lieu_stockage_arrive_id')->constrained('lieu_stockages')->onDelete('cascade');
            $table->text('commentaire')->nullable();
            $table->boolean('est_utilise')->default(false);
            $table->foreignId('user_id')->constrained('users');
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
        Schema::dropIfExists('bon_transferts');
    }
};
