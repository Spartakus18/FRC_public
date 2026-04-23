<?php

namespace App\Services;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Sortie;
use App\Models\AjustementStock\Stock;
use App\Models\BC\BonCommandeProduit;
use App\Models\BL\BonLivraison;
use App\Models\ConsommationGasoil;
use App\Models\Parametre\Materiel;
use App\Models\Produit\Vente;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BonLivraisonService
{
    /**
     * Crée un bon de livraison avec Vente, Sortie, mise à jour Stock et Matériel.
     *
     * @param array $data {
     *   numBL, date_livraison, heure_depart,
     *   vehicule_id, chauffeur_id, aide_chauffeur_id?,
     *   bon_commande_produit_id, quantite, PU,
     *   gasoil_depart, compteur_depart?,
     *   nbr_voyage, remarque?, user_name?
     * }
     * @throws \Exception
     */
    public function createBonLivraison(array $data): BonLivraison
    {
        return DB::transaction(function () use ($data) {
            $year = Carbon::parse($data['date_livraison'])->year;

            $bc = BonCommandeProduit::with([
                'bonCommande',
                'bonCommande.client',
                'bonCommande.destination',
                'unite',
                'lieuStockage',
                'article',
            ])->findOrFail($data['bon_commande_produit_id']);

            $quantiteBL = (float) $data['quantite'];

            // Vérifier quantité vs commande
            if ($quantiteBL > $bc->quantite) {
                throw new \Exception(
                    "La quantité livrée ({$quantiteBL}) dépasse la quantité commandée ({$bc->quantite})."
                );
            }

            // Vérifier cumul livraisons
            $dejaLivree = BonLivraison::where('bon_commande_produit_id', $bc->id)->sum('quantite');
            if (($dejaLivree + $quantiteBL) > $bc->quantite) {
                throw new \Exception(
                    "La quantité totale livrée (" . ($dejaLivree + $quantiteBL) .
                        ") dépasse la quantité commandée ({$bc->quantite})."
                );
            }

            // Vérifier stock
            $stock = Stock::where('article_id', $bc->article_id)
                ->where('lieu_stockage_id', $bc->lieu_stockage_id)
                ->lockForUpdate()
                ->firstOrFail();

            $article   = ArticleDepot::with('categorie')->findOrFail($bc->article_id);
            $stockApres = $stock->quantite - $quantiteBL;

            if ($stockApres < 0) {
                throw new \Exception(
                    "Stock insuffisant pour '{$bc->article->nom_article}'. " .
                        "Disponible: {$stock->quantite}, Demandé: {$quantiteBL}."
                );
            }

            $dejaLivreeTotale = $dejaLivree + $quantiteBL;

            // Créer le BL
            $bon = BonLivraison::create([
                'numBL'                  => $data['numBL'],
                'heure_depart'           => $data['heure_depart'],
                'vehicule_id'            => $data['vehicule_id'],
                'gasoil_depart'          => $data['gasoil_depart'],
                'date_livraison'         => $data['date_livraison'],
                'chauffeur_id'           => $data['chauffeur_id'],
                'aide_chauffeur_id'      => $data['aide_chauffeur_id'] ?? null,
                'bon_commande_produit_id' => $bc->id,
                'client_id'              => $bc->bonCommande->client_id ?? null,
                'PU'                     => $data['PU'],
                'quantite'               => $quantiteBL,
                'quantite_deja_livree'   => $data['quantite_deja_livree'] ?? $dejaLivreeTotale,
                'compteur_depart'        => $data['compteur_depart'] ?? null,
                'nbr_voyage'             => $data['nbr_voyage'],
                'remarque'               => $data['remarque'] ?? null,
                'isDelivred'             => $data['isDelivred'] ?? 0,
                'gasoil_arrive'          => $data['gasoil_arrive'] ?? null,
                'date_arriver'           => $data['date_arriver'] ?? null,
                'compteur_arrive'        => $data['compteur_arrive'] ?? null,
                'heure_arrive'           => $data['heure_arrive'] ?? null,
                'heure_chauffeur'        => $data['heure_chauffeur'] ?? null,
                'distance'               => $data['distance'] ?? null,
                'heure_machine'          => $data['heure_machine'] ?? 0,
                'consommation_reelle_par_heure' => $data['consommation_reelle_par_heure'] ?? null,
                'consommation_horaire_reference' => $data['consommation_horaire_reference'] ?? null,
                'ecart_consommation_horaire' => $data['ecart_consommation_horaire'] ?? null,
                'statut_consommation_horaire' => $data['statut_consommation_horaire'] ?? null,
                'consommation_totale'    => $data['consommation_totale'] ?? null,
                'consommation_destination_reference' => $data['consommation_destination_reference'] ?? null,
                'ecart_consommation_destination' => $data['ecart_consommation_destination'] ?? null,
                'statut_consommation_destination' => $data['statut_consommation_destination'] ?? null,

            ]);

            // Créer la Vente
            Vente::create([
                'date'               => $data['date_livraison'],
                'heure'              => $data['heure_depart'] ?? now()->toTimeString(),
                'client_id'          => $bc->bonCommande->client_id ?? null,
                'materiel_id'        => $data['vehicule_id'],
                'chauffeur_id'       => $data['chauffeur_id'],
                'destination'        => $bc->bonCommande->destination->nom_destination ?? '',
                'observation'        => "Livraison client (BL n°{$bon->numBL}/{$year}) - EN ATTENTE",
                'bl_id'              => $bon->id,
                'produit_id'         => $stock->id,
                'quantite'           => $quantiteBL,
                'stockDispo'         => $stock->quantite,
                'stockApresLivraison' => $stockApres,
            ]);

            // Créer la Sortie
            Sortie::create([
                'user_name'           => $data['user_name'] ?? 'import',
                'article_id'          => $bc->article_id,
                'categorie_article_id' => $article->categorie_id,
                'lieu_stockage_id'    => $bc->lieu_stockage_id,
                'quantite'            => $quantiteBL,
                'unite_id'            => $bc->unite_id,
                'motif'               => "Livraison client (BL n°{$bon->numBL}/{$year}) - EN ATTENTE",
                'sortie'              => $data['date_livraison'],
            ]);

            // Mettre à jour le stock
            $stock->update(['quantite' => $stockApres]);

            // Mettre à jour le gasoil et compteur du véhicule
            $vehicule = Materiel::find($data['vehicule_id']);
            if ($vehicule) {
                $vehicule->update([
                    'actuelGasoil'   => $data['gasoil_depart'],
                    'compteur_actuel' => $data['compteur_depart'] ?? $vehicule->compteur_actuel,
                ]);
            }

            return $bon;
        });
    }
}
