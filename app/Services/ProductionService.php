<?php

namespace App\Services;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Entrer;
use App\Models\AjustementStock\Stock;
use App\Models\ConsommationGasoil;
use App\Models\Parametre\Materiel;
use App\Models\Parametre\Unite;
use App\Models\Produit\ProductionMateriel;
use App\Models\Produit\ProductionProduit;
use App\Models\Produit\Produit;
use App\Models\User;
use App\Notifications\GasoilSeuilAtteint;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ProductionService
{
    /**
     * Crée une production complète (produits + matériels + stock + consommations).
     * Utilisé par ProduitController::store() et ExcelImportController.
     *
     * @param array $data {
     *   date_prod, heure_debut, heure_fin, remarque,
     *   create_user_id, update_user_id,
     *   produits: [{ produit_id, quantite, unite_id, lieu_stockage_id, observation }],
     *   materiels: [{ materiel_id, categorie_travail_id, heure_debut, heure_fin,
     *                 compteur_debut, compteur_fin, gasoil_debut, gasoil_fin, observation }]
     * }
     * @return Produit
     */
    public function createProduction(array $data): Produit
    {
        return DB::transaction(function () use ($data) {
            $production = Produit::create([
                'date_prod'      => $data['date_prod'],
                'isProduct'      => true,
                'heure_debut'    => $data['heure_debut'],
                'heure_fin'      => $data['heure_fin'],
                'remarque'       => $data['remarque'] ?? null,
                'create_user_id' => $data['create_user_id'] ?? null,
                'update_user_id' => $data['update_user_id'] ?? null,
            ]);

            if (!empty($data['produits'])) {
                foreach ($data['produits'] as $produitData) {
                    $this->attachProduit($production, $produitData);
                }
            }

            if (!empty($data['materiels'])) {
                foreach ($data['materiels'] as $materielData) {
                    $this->attachMateriel($production, $materielData);
                }
            }

            return $production->load([
                'produits.uniteProduction',
                'produits.articleDepot.uniteProduction',
                'produits.lieuStockage',
                'materiels.materiel',
                'materiels.categorieTravail',
            ]);
        });
    }

    /**
     * Crée un ProductionProduit, met à jour le stock et crée l'entrée.
     */
    private function attachProduit(Produit $production, array $produitData): void
    {
        $uniteStockageId = $this->getUniteM3Id();
        $quantite = (float) $produitData['quantite'];

        $productionProduit = ProductionProduit::create([
            'production_id'    => $production->id,
            'produit_id'       => $produitData['produit_id'],
            'quantite'         => $quantite,
            'unite_id'         => $produitData['unite_id'],
            'lieu_stockage_id' => $produitData['lieu_stockage_id'],
            'observation'      => $produitData['observation'] ?? null,
            'unite_stockage_id' => $uniteStockageId,
        ]);

        $article  = ArticleDepot::with('categorie')->findOrFail($productionProduit->produit_id);
        $categorie = $article->categorie_id;

        $stock = Stock::firstOrCreate(
            [
                'article_id'         => $productionProduit->produit_id,
                'lieu_stockage_id'   => $productionProduit->lieu_stockage_id,
                'categorie_article_id' => $categorie,
            ],
            ['quantite' => 0]
        );
        $stock->quantite += $quantite;
        $stock->save();

        Entrer::create([
            'user_name'           => $produitData['user_name'] ?? 'Système',
            'article_id'          => $productionProduit->produit_id,
            'categorie_article_id' => $categorie,
            'lieu_stockage_id'    => $productionProduit->lieu_stockage_id,
            'unite_id'            => $uniteStockageId,
            'quantite'            => $quantite,
            'entre'               => $production->date_prod ?? now()->toDateString(),
            'motif'               => 'Production n°' . $production->id,
        ]);
    }

    /**
     * Crée un ProductionMateriel, calcule les consommations,
     * met à jour le véhicule et crée la ConsommationGasoil.
     */
    private function attachMateriel(Produit $production, array $materielData): void
    {
        $vehicule = Materiel::findOrFail($materielData['materiel_id']);

        // Calcul consommation
        $consommationCm    = (float) $materielData['gasoil_debut'] - (float) $materielData['gasoil_fin'];
        $consommationTotale = $vehicule->convertirCmEnLitres($consommationCm);

        // Calcul heures de travail
        $heureDebut    = Carbon::parse($materielData['heure_debut']);
        $heureFin      = Carbon::parse($materielData['heure_fin']);
        $heuresTravail = $heureDebut->diffInHours($heureFin);

        $consommationReelleParHeure = $heuresTravail > 0
            ? $consommationTotale / $heuresTravail
            : 0;

        // Comparaison avec référence horaire
        [$ecartHoraire, $statutHoraire] = $this->calculerStatutConsommation(
            $consommationReelleParHeure,
            $vehicule->consommation_horaire ?? 0
        );

        $productionMateriel = ProductionMateriel::create([
            'production_id'                  => $production->id,
            'materiel_id'                    => $materielData['materiel_id'],
            'categorie_travail_id'           => $materielData['categorie_travail_id'],
            'heure_debut'                    => $materielData['heure_debut'],
            'heure_fin'                      => $materielData['heure_fin'],
            'compteur_debut'                 => $materielData['compteur_debut'] ?? null,
            'compteur_fin'                   => $materielData['compteur_fin'] ?? null,
            'gasoil_debut'                   => $materielData['gasoil_debut'],
            'gasoil_fin'                     => $materielData['gasoil_fin'],
            'observation'                    => $materielData['observation'] ?? null,
            'consommation_reelle_par_heure'  => $consommationReelleParHeure,
            'consommation_horaire_reference' => $vehicule->consommation_horaire,
            'ecart_consommation_horaire'     => $ecartHoraire,
            'statut_consommation_horaire'    => $statutHoraire,
            'consommation_totale'            => $consommationTotale,
        ]);

        // Mise à jour du véhicule
        $vehicule->update([
            'gasoil_consommation' => ($vehicule->gasoil_consommation ?? 0) + $consommationTotale,
            'actuelGasoil'        => $materielData['gasoil_fin'],
            'compteur_actuel'     => $materielData['compteur_fin'] ?? $vehicule->compteur_actuel,
        ]);

        // Notification seuil gasoil
        $vehicule->refresh();
        if ($vehicule->actuelGasoil <= $vehicule->seuil && !$vehicule->seuil_notified) {
            $admin = User::whereHas('role', fn($q) => $q->where('id', 1))->first();
            if ($admin) {
                Notification::send($admin, new GasoilSeuilAtteint($vehicule));
            }
            $vehicule->update(['seuil_notified' => true]);
        }

        // Enregistrement consommation gasoil
        $distance = isset($materielData['compteur_debut'], $materielData['compteur_fin'])
            ? ((float) $materielData['compteur_fin'] - (float) $materielData['compteur_debut'])
            : 0;

        ConsommationGasoil::create([
            'vehicule_id'                    => $materielData['materiel_id'],
            'quantite'                       => $consommationTotale,
            'distance_km'                    => $distance,
            'date_consommation'              => $production->date_prod ?? Carbon::now()->toDateString(),
            'consommation_reelle_par_heure'  => $consommationReelleParHeure,
            'consommation_horaire_reference' => $vehicule->consommation_horaire,
            'ecart_consommation_horaire'     => $ecartHoraire,
            'statut_consommation_horaire'    => $statutHoraire,
            'consommation_totale'            => $consommationTotale,
            'bon_livraison_id'               => null,
            'transfert_produit_id'           => null,
            'production_materiel_id'         => $productionMateriel->id,
        ]);
    }

    /**
     * Calcule l'écart et le statut de consommation horaire.
     * @return array{0: float, 1: string}
     */
    private function calculerStatutConsommation(float $reelle, float $reference): array
    {
        if ($reference <= 0) {
            return [0, 'normal'];
        }

        $ecart      = $reelle - $reference;
        $pourcentage = ($ecart / $reference) * 100;

        $statut = match (true) {
            $pourcentage > 15  => 'trop_elevee',
            $pourcentage < -15 => 'trop_basse',
            default            => 'normale',
        };

        return [$ecart, $statut];
    }

    private function getUniteM3Id(): int
    {
        $unite = Unite::where('nom_unite', 'like', '%m³%')
            ->orWhere('nom_unite', 'like', '%m3%')
            ->orWhere('nom_unite', 'like', '%mètre cube%')
            ->first();

        return $unite ? $unite->id : 1;
    }
}
