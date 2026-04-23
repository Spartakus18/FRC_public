<?php

namespace App\Services;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Sortie;
use App\Models\AjustementStock\Stock;
use App\Models\BC\BonGasoil;
use App\Models\Consommable\Gasoil;
use App\Models\Parametre\Materiel;
use App\Models\Parametre\Unite;
use App\Notifications\GasoilSeuilAtteint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class GasoilService
{
    public function __construct(
        private GasoilConversionService $conversion
    ) {}

    /**
     * Crée un versement de gasoil (bon + opération + mise à jour stock + matériel).
     *
     * @param array $data {
     *   num_bon, materiel_id_cible, quantite,
     *   source_lieu_stockage_id? (null = station),
     *   prix_gasoil?, ajouter_par?
     * }
     * @throws \Exception
     */
    public function createVersement(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // ⚠️ Vérification explicite avant findOrFail
            if (empty($data['materiel_id_cible'])) {
                throw new \Exception(
                    "materiel_id_cible est requis. Vérifiez que materiel_nom_cible correspond à un matériel existant."
                );
            }

            $materiel = Materiel::findOrFail($data['materiel_id_cible']);

            $quantiteLitres = (float) $data['quantite'];
            $capaciteCm     = (float) $materiel->capaciteCm;
            $quantiteCm     = GasoilConversionService::literToCm($quantiteLitres, $capaciteCm);

            // Créer le bon
            $bon = BonGasoil::create([
                'num_bon'                => $data['num_bon'],
                'quantite'               => $quantiteLitres,
                'source_lieu_stockage_id' => $data['source_lieu_stockage_id'] ?? null,
                'ajouter_par'            => $data['ajouter_par'] ?? 'import',
                'is_consumed'            => true,
            ]);

            $isStation   = empty($data['source_lieu_stockage_id']);
            $sourceLieuId = $isStation ? null : $data['source_lieu_stockage_id'];

            // Créer l'opération gasoil
            $gasoilAvant = (float) $materiel->actuelGasoil;
            $gasoilApres = $gasoilAvant + $quantiteCm;

            $gasoil = Gasoil::create([
                'bon_id'                 => $bon->id,
                'type_operation'         => 'versement',
                'materiel_id_cible'      => $materiel->id,
                'source_lieu_stockage_id' => $sourceLieuId,
                'source_station'         => $isStation ? 'station' : null,
                'quantite'               => $quantiteLitres,
                'prix_gasoil'            => $data['prix_gasoil'] ?? null,
                'ajouter_par'            => $data['ajouter_par'] ?? 'import',
                'materiel_go_avant'      => $gasoilAvant,
                'materiel_go_apres'      => $gasoilApres,
                'is_consumed'            => true,
            ]);

            // Mettre à jour le matériel
            $materiel->actuelGasoil = $gasoilApres;
            $materiel->save();

            // Décrémenter le stock et créer une sortie si source = lieu stockage
            if (!$isStation) {
                $articleGasoil = ArticleDepot::with('categorie')
                    ->whereRaw('LOWER(nom_article) = ?', ['gasoil'])
                    ->firstOrFail();

                $stock = Stock::where('article_id', $articleGasoil->id)
                    ->where('lieu_stockage_id', $sourceLieuId)
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->quantite < $quantiteLitres) {
                    $dispo = $stock ? $stock->quantite : 0;
                    throw new \Exception(
                        "Stock gasoil insuffisant au lieu de stockage. " .
                            "Disponible: {$dispo} L, Demandé: {$quantiteLitres} L."
                    );
                }

                $stockAvant = (float) $stock->quantite;
                $stock->quantite -= $quantiteLitres;
                $stock->save();

                $gasoil->update([
                    'quantite_stock_avant' => $stockAvant,
                    'quantite_stock_apres' => $stock->quantite,
                ]);

                $uniteLitre = Unite::whereIn(DB::raw('LOWER(nom_unite)'), ['l', 'litre'])->first();

                Sortie::create([
                    'user_name'           => $data['ajouter_par'] ?? 'import',
                    'article_id'          => $articleGasoil->id,
                    'categorie_article_id' => $articleGasoil->categorie_id,
                    'lieu_stockage_id'    => $sourceLieuId,
                    'quantite'            => $quantiteLitres,
                    'unite_id'            => $uniteLitre?->id,
                    'motif'               => "Versement gasoil import - Bon n° {$bon->num_bon}",
                    'sortie'              => $data['date_operation'] ?? now()->toDateString(),
                ]);
            }

            // Notification seuil
            if ($materiel->actuelGasoil <= $materiel->seuil && !$materiel->seuil_notified) {
                $admins = \App\Models\User::where('role_id', 1)->get();
                Notification::send($admins, new GasoilSeuilAtteint($materiel));
                $materiel->update(['seuil_notified' => true]);
            }

            return ['bon' => $bon, 'gasoil' => $gasoil];
        });
    }
}
