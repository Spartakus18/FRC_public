<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('operation_atelier_mecas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('materiel_id')->constrained('materiels')->onDelete('cascade');
            $table->foreignId('stock_id')->nullable()->constrained('stocks')->nullOnDelete();
            $table->decimal('gasoil_retirer', 10, 2);
            $table->decimal('quantite_retiree_cm', 10, 2);
            $table->decimal('reste_gasoil', 10, 2);
            $table->decimal('stock_atelier_avant', 10, 2)->default(0);
            $table->decimal('stock_atelier_apres', 10, 2)->default(0);
            $table->boolean('is_remis')->default(false);
            $table->foreignId('remis_materiel_id')->nullable()->constrained('materiels')->nullOnDelete();
            $table->foreignId('remis_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('remis_at')->nullable();
            $table->text('commentaire')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('operation_at')->nullable();
            $table->timestamps();

            $table->index(['materiel_id', 'operation_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('operation_atelier_mecas');
    }
};
