<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::create('sorties', function (Blueprint $table) {
            $table->id();
            $table->string('user_name');
            $table->string('demande_par')->nullable();
            $table->string('sortie_par')->nullable();
            $table->foreignId('article_id')->constrained('article_depots')->onDelete('cascade');
            $table->foreignId('categorie_article_id')->constrained('categorie_articles')->onDelete('cascade');
            $table->foreignId('lieu_stockage_id')->constrained('lieu_stockages')->onDelete('cascade');
            $table->foreignId('unite_id')->constrained('unites');
            $table->decimal('quantite', 10, 2);
            $table->string('motif')->nullable();
            $table->timestamp('sortie')->default(DB::raw('CURRENT_TIMESTAMP'));
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
        Schema::dropIfExists('sorties');
    }
};
