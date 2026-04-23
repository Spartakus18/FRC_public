<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Index pour materiels
        Schema::table('materiels', function (Blueprint $table) {
            $table->index(['nom_materiel']);
            $table->index(['categorie']);
            $table->index(['status']);
            $table->index(['created_at']);
        });

        // Index pour pneus
        Schema::table('pneus', function (Blueprint $table) {
            $table->index(['materiel_id']);
            $table->index(['situation']);
            $table->index(['etat']);
            $table->index(['date_mise_en_service']);
        });

        // Index pour gasoils
        Schema::table('gasoils', function (Blueprint $table) {
            $table->index(['materiel_id_cible']);
            $table->index(['source_lieu_stockage_id']);
            $table->index(['created_at']);
            $table->index(['ajouter_par']);
        });

        // Index pour huiles
        Schema::table('huiles', function (Blueprint $table) {
            $table->index(['materiel_id_cible']);
            $table->index(['subdivision_id_cible']);
            $table->index(['article_versement_id']);
            $table->index(['created_at']);
            $table->index(['ajouter_par']);
        });

        // Index pour historique_pneus
        Schema::table('historique_pneus', function (Blueprint $table) {
            $table->index(['pneu_id']);
            $table->index(['ancien_materiel_id']);
            $table->index(['nouveau_materiel_id']);
            $table->index(['type_action']);
            $table->index(['date_action']);
        });
    }

    public function down()
    {
        // Supprimer les index dans l'ordre inverse
        Schema::table('historique_pneus', function (Blueprint $table) {
            $table->dropIndex(['pneu_id']);
            $table->dropIndex(['ancien_materiel_id']);
            $table->dropIndex(['nouveau_materiel_id']);
            $table->dropIndex(['type_action']);
            $table->dropIndex(['date_action']);
        });

        Schema::table('huiles', function (Blueprint $table) {
            $table->dropIndex(['materiel_id_cible']);
            $table->dropIndex(['subdivision_id_cible']);
            $table->dropIndex(['article_versement_id']);
            $table->dropIndex(['source_lieu_stockage_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['ajouter_par']);
        });

        Schema::table('gasoils', function (Blueprint $table) {
            $table->dropIndex(['materiel_id_cible']);
            $table->dropIndex(['source_lieu_stockage_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['ajouter_par']);
        });

        Schema::table('pneus', function (Blueprint $table) {
            $table->dropIndex(['materiel_id']);
            $table->dropIndex(['situation']);
            $table->dropIndex(['etat']);
            $table->dropIndex(['date_mise_en_service']);
        });

        Schema::table('materiels', function (Blueprint $table) {
            $table->dropIndex(['nom_materiel']);
            $table->dropIndex(['categorie']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });
    }
};
