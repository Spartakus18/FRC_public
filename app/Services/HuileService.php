<?php

namespace App\Services;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Sortie;
use App\Models\AjustementStock\Stock;
use App\Models\BC\BonHuile;
use App\Models\Consommable\Huile;
use App\Models\Parametre\Unite;
use Illuminate\Support\Facades\DB;

class HuileService
{
    /**
     * Crée un versement d'huile (bon + opération + mise à jour stock).
     *
     * @param array $data {
     *   num_bon, materiel_id_cible, subdivision_id_cible?,
     *   article_versement_id, quantite,
     *   source_lieu_stockage_id, ajouter_par?
     * }
     * @throws \Exception
     */
    public function createVersement(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $articleHuile = ArticleDepot::findOrFail($data['article_versement_id']);
            $sourceLieuId = $data['source_lieu_stockage_id'];
            $quantite     = (float) $data['quantite'];

            // Vérifier le stock
            $stock = Stock::where('article_id', $articleHuile->id)
                ->where('lieu_stockage_id', $sourceLieuId)
                ->lockForUpdate()
                ->first();

            if (!$stock || $stock->quantite < $quantite) {
                $dispo = $stock ? $stock->quantite : 0;
                throw new \Exception(
                    "Stock huile insuffisant pour '{$articleHuile->nom_article}'. " .
                    "Disponible: {$dispo} L, Demandé: {$quantite} L."
                );
            }

            // Créer le bon
            $bon = BonHuile::create([
                'num_bon'                => $data['num_bon'],
                'source_lieu_stockage_id' => $sourceLieuId,
                'ajouter_par'            => $data['ajouter_par'] ?? 'import',
                'is_consumed'            => true,
            ]);

            // Créer l'opération huile
            $stockAvant = (float) $stock->quantite;
            $stockApres = $stockAvant - $quantite;

            $huile = Huile::create([
                'bon_id'                 => $bon->id,
                'type_operation'         => 'versement',
                'materiel_id_cible'      => $data['materiel_id_cible'],
                'subdivision_id_cible'   => $data['subdivision_id_cible'] ?? null,
                'article_versement_id'   => $articleHuile->id,
                'source_lieu_stockage_id' => $sourceLieuId,
                'quantite'               => $quantite,
                'ajouter_par'            => $data['ajouter_par'] ?? 'import',
                'quantite_stock_avant'   => $stockAvant,
                'quantite_stock_apres'   => $stockApres,
                'is_consumed'            => true,
            ]);

            // Décrémenter le stock
            $stock->quantite = $stockApres;
            $stock->save();

            // Créer la sortie
            $uniteLitre = Unite::whereIn(DB::raw('LOWER(nom_unite)'), ['l', 'litre'])->first();

            Sortie::create([
                'user_name'           => $data['ajouter_par'] ?? 'import',
                'article_id'          => $articleHuile->id,
                'categorie_article_id' => $articleHuile->categorie_id,
                'lieu_stockage_id'    => $sourceLieuId,
                'quantite'            => $quantite,
                'unite_id'            => $uniteLitre?->id,
                'motif'               => "Versement huile import - Bon n° {$bon->num_bon}",
                'sortie'              => $data['date_operation'] ?? now()->toDateString(),
            ]);

            return ['bon' => $bon, 'huile' => $huile];
        });
    }
}
