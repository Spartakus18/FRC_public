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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('article_depots')->onDelete('cascade');
            $table->foreignId('categorie_article_id')->constrained('categorie_articles')->onDelete('cascade');
            $table->foreignId('lieu_stockage_id')->nullable()->constrained('lieu_stockages')->onDelete('cascade');
            $table->decimal('quantite', 10, 2)->default(0);
            $table->unique(['article_id', 'lieu_stockage_id']); // un article par lieu

            // pour l'atelier mecanique
            $table->boolean('isAtelierMeca')->default(false);

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
        Schema::dropIfExists('stocks');
    }
};
