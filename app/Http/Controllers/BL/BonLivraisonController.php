<?php

namespace App\Http\Controllers\BL;

use App\Exports\BonLivraisonExport;
use App\Http\Controllers\Controller;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\BC\Bon_commande;
use App\Models\BL\BonLivraison;
use App\Models\Produit\Vente;
use App\Models\AjustementStock\Stock;
use App\Models\AjustementStock\Sortie;
use App\Models\BC\BonCommandeProduit;
use App\Models\ConsommationGasoil;
use App\Models\Location\AideChauffeur;
use App\Models\Location\Conducteur;
use App\Models\Parametre\Client;
use App\Models\Parametre\Materiel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class BonLivraisonController extends Controller
{
    // 1️⃣ Liste des BL avec filtres et pagination
    public function index(Request $request)
    {
        $query = BonLivraison::with([
            'chauffeur',
            'vehicule',
            'aideChauffeur',
            'bonCommandeProduit',
            'bonCommandeProduit.bonCommande',
            'bonCommandeProduit.bonCommande.client',
            'bonCommandeProduit.bonCommande.destination',
            'bonCommandeProduit.article',
            'bonCommandeProduit.unite',
            'bonCommandeProduit.lieuStockage',
        ]);

        // Filtre par recherche (numBL, client, chauffeur)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numBL', 'like', '%' . $search . '%')
                    ->orWhereHas('bonCommandeProduit.bonCommande.client', function ($q2) use ($search) {
                        $q2->where('nom_client', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('chauffeur', function ($q3) use ($search) {
                        $q3->where('nom_conducteur', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('vehicule', function ($q3) use ($search) {
                        $q3->where('nom_materiel', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('bonCommandeProduit.bonCommande', function ($q3) use ($search) {
                        $q3->where('numero', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filtre par date de début (date_livraison)
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('date_livraison', '>=', $request->date_start);
        }

        // Filtre par date de fin (date_livraison)
        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('date_livraison', '<=', $request->date_end);
        }

        // Exclure le client "Clients divers" si demandé
        if ($request->boolean('exclude_client_divers')) {
            $query->whereHas('bonCommandeProduit.bonCommande.client', function ($q) {
                $q->where('nom_client', '!=', 'Clients divers');
            });
        }

        // Tri par date de livraison décroissante
        $query->orderBy('date_livraison', 'desc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $bonLivraisons = $query->paginate($perPage);

        return response()->json($bonLivraisons);
    }

    // 2️⃣ Données nécessaires pour créer un BL
    public function create(Request $request)
    {
        // Vérifier si une date est fournie, sinon utiliser aujourd'hui
        $dateLivraison = $request->has('date_livraison')
            ? Carbon::parse($request->date_livraison)
            : Carbon::now();

        // Calcul du prochain numéro BL suggéré POUR L'ANNÉE DE LA DATE
        $nextNumBL = $this->getSuggestedNumBLForYear($dateLivraison->year);

        return response()->json([
            'chauffeurs' => Conducteur::all(),
            'vehicules' => Materiel::all(),
            'aidesChauffeur' => AideChauffeur::all(),
            'bonCommandeProduit' => BonCommandeProduit::with(['bonCommande', 'bonCommande.client', 'unite', 'lieuStockage', 'article'])->get(),
            'clients' => Client::all(),
            'nextNumBL' => $nextNumBL,
            'currentYear' => $dateLivraison->year,
        ]);
    }

    // 3️⃣ Méthode pour obtenir le numéro BL suggéré pour une année spécifique
    private function getSuggestedNumBLForYear($year)
    {
        try {
            // Trouver le dernier BL de l'année donnée par ordre numérique
            $lastBL = BonLivraison::whereYear('date_livraison', $year)
                ->orderByRaw('CAST(numBL AS UNSIGNED) DESC')
                ->first();

            if (!$lastBL || !$lastBL->numBL || !is_numeric($lastBL->numBL)) {
                $nextNum = 1;
            } else {
                $nextNum = intval($lastBL->numBL) + 1;
            }

            return $nextNum;
        } catch (\Exception $e) {
            return 1;
        }
    }

    // Exporter les BL filtrés en Excel
    public function exportExcel(Request $request)
    {
        return Excel::download(new BonLivraisonExport($request), 'bons_livraison.xlsx');
    }

    // 4️⃣ Créer un BL
    public function store(Request $request)
    {
        // Règles de validation
        $validationRules = [
            'numBL' => [
                'required',
                'numeric',
                'min:1'
            ],
            'heure_depart' => 'required',
            'vehicule_id' => 'required|integer|exists:materiels,id',
            'gasoil_depart' => 'required|numeric',
            'date_livraison' => [
                'required',
                'date',
                'after_or_equal:' . Carbon::now()->subDays(2)->toDateString(),
                'before_or_equal:' . Carbon::now()->toDateString(),
            ],
            'chauffeur_id' => 'required|exists:conducteurs,id',
            'aide_chauffeur_id' => 'nullable|exists:aide_chauffeurs,id',
            'bon_commande_produit_id' => 'required|exists:bon_commande_produits,id',
            'PU' => 'required|numeric|min:0',
            'quantite' => 'required|numeric|min:0.01',
            'compteur_depart' => 'nullable|numeric',
            'nbr_voyage' => 'required|string',
            'remarque' => 'nullable|string',
        ];

        // Extraire l'année de la date de livraison
        $year = Carbon::parse($request->date_livraison)->year;

        // Ajouter la règle d'unicité pour l'année
        $validationRules['numBL'][] = Rule::unique('bon_livraisons')->where(function ($query) use ($year) {
            return $query->whereYear('date_livraison', $year);
        })->ignore($request->id ?? 0);

        $request->validate($validationRules);

        $bc = BonCommandeProduit::with(['bonCommande', 'bonCommande.client', 'unite', 'lieuStockage', 'article'])
            ->where('id', $request->bon_commande_produit_id)
            ->firstOrFail();

        $stock = Stock::where('article_id', $bc->article_id)
            ->where('lieu_stockage_id', $bc->lieu_stockage_id)
            ->firstOrFail();

        $article = ArticleDepot::with('categorie')->where('id', $bc->article_id)->firstOrFail();
        $categorie = $article->categorie_id;

        $quantiteBL = $request->quantite;

        // Vérifier que la quantité du BL ne dépasse pas la quantité commandée dans le BC
        if ($quantiteBL > $bc->quantite) {
            return response()->json([
                'message' => 'La quantité livrée ne peut pas dépasser la quantité commandée (' . $bc->quantite . ')'
            ], 400);
        }

        // Calculer la quantité déjà livrée pour ce BC
        $quantiteDejaLivree = BonLivraison::where('bon_commande_produit_id', $bc->id)
            ->where('id', '!=', $request->id ?? 0)
            ->sum('quantite');

        // Vérifier que la nouvelle livraison ne dépasse pas la quantité commandée
        if (($quantiteDejaLivree + $quantiteBL) > $bc->quantite) {
            return response()->json([
                'message' => 'La quantité totale livrée (' . ($quantiteDejaLivree + $quantiteBL) . ') dépasse la quantité commandée (' . $bc->quantite . ')'
            ], 400);
        }

        $stockApres = $stock->quantite - $quantiteBL;

        if ($stockApres < 0) {
            return response()->json([
                'message' => 'Stock insuffisant pour le produit: ' . $bc->article->nom_article
            ], 400);
        }

        // Statut initial : non livré (0)
        $isDelivred = 0;

        // Calculer la quantité déjà livrée totale
        $quantiteDejaLivreeTotale = $quantiteDejaLivree + $quantiteBL;

        $bon = BonLivraison::create([
            'numBL' => $request->numBL,
            'heure_depart' => $request->heure_depart,
            'heure_arrive' => null,
            'vehicule_id' => $request->vehicule_id,
            'gasoil_depart' => $request->gasoil_depart,
            'gasoil_arrive' => null,
            'date_livraison' => $request->date_livraison,
            'date_arriver' => null,
            'chauffeur_id' => $request->chauffeur_id,
            'aide_chauffeur_id' => $request->aide_chauffeur_id,
            'bon_commande_produit_id' => $bc->id,
            'client_id' => $request->client_id,
            'PU' => $request->PU,
            'quantite' => $quantiteBL,
            'quantite_deja_livree' => $quantiteDejaLivreeTotale,
            'compteur_depart' => $request->compteur_depart,
            'compteur_arrive' => null,
            'nbr_voyage' => $request->nbr_voyage,
            'remarque' => $request->remarque,
            'isDelivred' => $isDelivred,
            'heure_chauffeur' => null,
            'distance' => null,
            'heure_machine' => null,
            'consommation_totale' => null,
            'consommation_reelle_par_heure' => null,
            'consommation_horaire_reference' => null,
            'ecart_consommation_horaire' => null,
            'statut_consommation_horaire' => null,
            'consommation_destination_reference' => null,
            'ecart_consommation_destination' => null,
            'statut_consommation_destination' => null,
        ]);

        // Créer vente et sortie immédiatement
        Vente::create([
            'date' => now()->toDateString(),
            'heure' => now()->toTimeString(),
            'client_id' => $request->client_id,
            'materiel_id' => $request->vehicule_id,
            'chauffeur_id' => $request->chauffeur_id,
            'destination' => $bc->bonCommande->destination->nom_destination,
            'observation' => 'Livraison client (BL n°' . $bon->numBL . '/' . $year . ') - EN ATTENTE',
            'bl_id' => $bon->id,
            'produit_id' => $stock->id,
            'quantite' => $quantiteBL,
            'stockDispo' => $stock->quantite,
            'stockApresLivraison' => $stockApres,
        ]);

        Sortie::create([
            'user_name' => Auth::check() ? Auth::user()->nom : 'Système',
            'article_id' => $bc->article_id,
            'categorie_article_id' => $categorie,
            'lieu_stockage_id' => $bc->lieu_stockage_id,
            'quantite' => $quantiteBL,
            'unite_id' => $bc->unite_id,
            'motif' => 'Livraison client (BL n°' . $bon->numBL . '/' . $year . ') - EN ATTENTE',
            'sortie' => now()->toDateString(),
        ]);

        // Mettre à jour le stock
        $stock->update(['quantite' => $stockApres]);

        // Mettre à jour le gasoil du véhicule
        $vehicule = Materiel::find($request->vehicule_id);
        if ($vehicule) {
            $vehicule->update([
                'actuelGasoil' => $request->gasoil_depart,
                'compteur_actuel' => $request->compteur_depart ?? $vehicule->compteur_actuel,
            ]);
        }

        return response()->json([
            'message' => 'BL créé avec succès ! En attente de validation.',
            'BL' => $bon->load(['chauffeur', 'vehicule', 'aideChauffeur', 'bonCommandeProduit'])
        ]);
    }

    // 5️⃣ Mettre à jour un BL
    public function update(Request $request, $id)
    {
        $bon = BonLivraison::findOrFail($id);

        // Extraire l'année de la date de livraison
        $year = $request->has('date_livraison')
            ? Carbon::parse($request->date_livraison)->year
            : Carbon::parse($bon->date_livraison)->year;

        // Règles de validation de base
        $validationRules = [
            'numBL' => [
                'required',
                'numeric',
                'min:1'
            ],
            'heure_depart' => 'required',
            'vehicule_id' => 'required|integer|exists:materiels,id',
            'gasoil_depart' => 'required|numeric',
            'date_livraison' => [
                'required',
                'date',
            ],
            'chauffeur_id' => 'required|exists:conducteurs,id',
            'aide_chauffeur_id' => 'nullable|exists:aide_chauffeurs,id',
            'bon_commande_produit_id' => 'required|exists:bon_commande_produits,id',
            'PU' => 'required|numeric|min:0',
            'quantite' => 'required|numeric|min:0.01',
            'compteur_depart' => 'nullable|numeric',
            'nbr_voyage' => 'required|string',
            'remarque' => 'nullable|string',
        ];

        // Si le BL est validé, ajouter les règles pour les champs d'arrivée
        if ($bon->isDelivred) {
            $validationRules = array_merge($validationRules, [
                'heure_arrive' => 'required',
                'gasoil_arrive' => 'required|numeric',
                'date_arriver' => 'required|date',
                'distance' => 'nullable|numeric|min:0',
                'compteur_arrive' => 'nullable|numeric',
                'heure_chauffeur' => 'nullable|numeric',
                'heure_machine' => 'nullable|numeric',
            ]);
        }

        // Ajouter la règle d'unicité pour l'année
        $validationRules['numBL'][] = Rule::unique('bon_livraisons')->where(function ($query) use ($year) {
            return $query->whereYear('date_livraison', $year);
        })->ignore($id);

        $request->validate($validationRules);

        $bc = BonCommandeProduit::with(['bonCommande', 'bonCommande.client', 'unite', 'lieuStockage', 'article'])
            ->where('id', $request->bon_commande_produit_id)
            ->firstOrFail();

        $stock = Stock::where('article_id', $bc->article_id)
            ->where('lieu_stockage_id', $bc->lieu_stockage_id)
            ->firstOrFail();

        $article = ArticleDepot::with('categorie')->where('id', $bc->article_id)->firstOrFail();
        $categorie = $article->categorie_id;

        $quantiteBL = $request->quantite;

        if ($quantiteBL > $bc->quantite) {
            return response()->json([
                'message' => 'La quantité livrée ne peut pas dépasser la quantité commandée (' . $bc->quantite . ')'
            ], 400);
        }

        // Calculer la quantité déjà livrée pour ce BC (sans le BL actuel)
        $quantiteDejaLivreeSansCeBL = BonLivraison::where('bon_commande_produit_id', $bc->id)
            ->where('id', '!=', $bon->id)
            ->sum('quantite');

        // Vérifier que la nouvelle quantité ne dépasse pas la commande
        if (($quantiteDejaLivreeSansCeBL + $quantiteBL) > $bc->quantite) {
            return response()->json([
                'message' => 'La quantité totale livrée (' . ($quantiteDejaLivreeSansCeBL + $quantiteBL) . ') dépasse la quantité commandée (' . $bc->quantite . ')'
            ], 400);
        }

        // Annuler l'ancienne sortie (restaurer le stock)
        $stock->quantite += $bon->quantite;
        $stock->save();

        // Supprimer l'ancienne vente et sortie
        Vente::where('bl_id', $bon->id)->delete();
        Sortie::where('motif', 'LIKE', '%BL n°' . $bon->numBL . '%')->delete();

        // Si BL validé, supprimer aussi l'ancienne consommation
        if ($bon->isDelivred) {
            ConsommationGasoil::where('bon_livraison_id', $bon->id)->delete();
        }

        $stockApres = $stock->quantite - $quantiteBL;
        if ($stockApres < 0) {
            return response()->json([
                'message' => 'Stock insuffisant pour le produit: ' . $bc->article->nom_article
            ], 400);
        }

        // Calculer la nouvelle quantité déjà livrée totale
        $quantiteDejaLivreeTotale = $quantiteDejaLivreeSansCeBL + $quantiteBL;

        // Données de base à mettre à jour
        $updateData = [
            'numBL' => $request->numBL,
            'heure_depart' => $request->heure_depart,
            'vehicule_id' => $request->vehicule_id,
            'gasoil_depart' => $request->gasoil_depart,
            'date_livraison' => $request->date_livraison,
            'chauffeur_id' => $request->chauffeur_id,
            'aide_chauffeur_id' => $request->aide_chauffeur_id,
            'PU' => $request->PU,
            'quantite' => $quantiteBL,
            'quantite_deja_livree' => $quantiteDejaLivreeTotale,
            'compteur_depart' => $request->compteur_depart,
            'nbr_voyage' => $request->nbr_voyage,
            'remarque' => $request->remarque,
        ];

        // Si le BL était validé, mettre à jour les champs d'arrivée
        if ($bon->isDelivred && $request->has('heure_arrive')) {
            // Recalculer les consommations
            $vehicule = Materiel::with('pneus')->find($request->vehicule_id);
            $destination = $bc->bonCommande->destination;

            $updateData = array_merge($updateData, [
                'heure_arrive' => $request->heure_arrive,
                'gasoil_arrive' => $request->gasoil_arrive,
                'date_arriver' => $request->date_arriver,
                'compteur_arrive' => $request->compteur_arrive,
                'heure_chauffeur' => $request->heure_chauffeur,
                'heure_machine' => $request->heure_machine,
                'distance' => $request->distance ?? null,
            ]);

            // Recalculer la consommation totale en litres
            $consommationCm = $request->gasoil_depart - $request->gasoil_arrive;
            $consommationTotale = $vehicule->convertirCmEnLitres($consommationCm);
            $updateData['consommation_totale'] = $consommationTotale;

            // Recalculer la consommation horaire si on a l'heure machine
            if ($request->heure_machine > 0) {
                $consommationReelleParHeure = $consommationTotale / $request->heure_machine;
                $updateData['consommation_reelle_par_heure'] = $consommationReelleParHeure;

                $consommationHoraireReference = $vehicule->consommation_horaire ?? 0;
                $updateData['consommation_horaire_reference'] = $consommationHoraireReference;

                if ($consommationHoraireReference > 0) {
                    $ecartConsommationHoraire = $consommationReelleParHeure - $consommationHoraireReference;
                    $pourcentageEcartHoraire = ($ecartConsommationHoraire / $consommationHoraireReference) * 100;

                    $updateData['ecart_consommation_horaire'] = $ecartConsommationHoraire;

                    if ($pourcentageEcartHoraire > 15) {
                        $updateData['statut_consommation_horaire'] = 'trop_elevee';
                    } elseif ($pourcentageEcartHoraire < -15) {
                        $updateData['statut_consommation_horaire'] = 'trop_basse';
                    } else {
                        $updateData['statut_consommation_horaire'] = 'normale';
                    }
                }
            }

            // Recalculer la consommation par destination
            $consommationDestinationReference = $destination->consommation_reference ?? 0;
            $updateData['consommation_destination_reference'] = $consommationDestinationReference;

            if ($consommationDestinationReference > 0) {
                $ecartConsommationDestination = $consommationTotale - $consommationDestinationReference;
                $pourcentageEcartDestination = ($ecartConsommationDestination / $consommationDestinationReference) * 100;

                $updateData['ecart_consommation_destination'] = $ecartConsommationDestination;

                if ($pourcentageEcartDestination > 15) {
                    $updateData['statut_consommation_destination'] = 'trop_elevee';
                } elseif ($pourcentageEcartDestination < -15) {
                    $updateData['statut_consommation_destination'] = 'trop_basse';
                } else {
                    $updateData['statut_consommation_destination'] = 'normale';
                }
            }
        }

        $bon->update($updateData);

        // Recréer vente et sortie avec le bon statut
        $statutVente = $bon->isDelivred ? ' - VALIDÉ' : ' - EN ATTENTE';

        Vente::create([
            'date' => now()->toDateString(),
            'heure' => now()->toTimeString(),
            'client_id' => $request->client_id,
            'materiel_id' => $request->vehicule_id,
            'chauffeur_id' => $request->chauffeur_id,
            'destination' => $bc->bonCommande->destination->nom_destination,
            'observation' => 'Livraison client (BL n°' . $bon->numBL . '/' . $year . ')' . $statutVente,
            'bl_id' => $bon->id,
            'produit_id' => $stock->id,
            'quantite' => $quantiteBL,
            'stockDispo' => $stock->quantite,
            'stockApresLivraison' => $stockApres,
        ]);

        Sortie::create([
            'user_name' => Auth::check() ? Auth::user()->nom : 'Système',
            'article_id' => $bc->article_id,
            'categorie_article_id' => $categorie,
            'lieu_stockage_id' => $bc->lieu_stockage_id,
            'quantite' => $quantiteBL,
            'unite_id' => $bc->unite_id,
            'motif' => 'Livraison client (BL n°' . $bon->numBL . '/' . $year . ')' . $statutVente,
            'sortie' => now()->toDateString(),
        ]);

        $stock->update(['quantite' => $stockApres]);

        // Mettre à jour le gasoil du véhicule
        $vehicule = Materiel::find($request->vehicule_id);
        if ($vehicule) {
            $vehicule->update([
                'actuelGasoil' => $request->gasoil_depart,
                'compteur_actuel' => $request->compteur_depart ?? $vehicule->compteur_actuel,
            ]);

            // Si BL validé, mettre à jour aussi le gasoil arrivée et compteur arrivée
            if ($bon->isDelivred && $request->has('gasoil_arrive')) {
                $vehicule->update([
                    'actuelGasoil' => $request->gasoil_arrive,
                    'compteur_actuel' => $request->compteur_arrive ?? $vehicule->compteur_actuel,
                ]);
            }
        }

        // Recréer l'enregistrement de consommation si BL validé
        if ($bon->isDelivred && $request->has('heure_arrive')) {
            ConsommationGasoil::create([
                'vehicule_id' => $bon->vehicule_id,
                'quantite' => $updateData['consommation_totale'] ?? 0,
                'distance_km' => $request->distance ?? 0,
                'date_consommation' => $request->date_arriver ?? Carbon::now()->toDateString(),
                'consommation_reelle_par_heure' => $updateData['consommation_reelle_par_heure'] ?? 0,
                'consommation_horaire_reference' => $updateData['consommation_horaire_reference'] ?? 0,
                'ecart_consommation_horaire' => $updateData['ecart_consommation_horaire'] ?? 0,
                'statut_consommation_horaire' => $updateData['statut_consommation_horaire'] ?? 'normal',
                'consommation_totale' => $updateData['consommation_totale'] ?? 0,
                'consommation_destination_reference' => $updateData['consommation_destination_reference'] ?? 0,
                'ecart_consommation_destination' => $updateData['ecart_consommation_destination'] ?? 0,
                'statut_consommation_destination' => $updateData['statut_consommation_destination'] ?? 'normal',
                'destination_id' => $destination->id ?? null,
                'bon_livraison_id' => $bon->id,
                'transfert_produit_id' => null,
                'production_materiel_id' => null,
            ]);
        }

        return response()->json([
            'message' => 'BL mis à jour avec succès !',
            'BL' => $bon->load(['chauffeur', 'vehicule', 'aideChauffeur', 'bonCommandeProduit'])
        ]);
    }

    // 6️⃣ Supprimer un BL
    public function destroy($id)
    {
        $bon = BonLivraison::with(['bonCommandeProduit'])->findOrFail($id);

        $stock = Stock::where('article_id', $bon->bonCommandeProduit->article_id)
            ->where('lieu_stockage_id', $bon->bonCommandeProduit->lieu_stockage_id)
            ->firstOrFail();

        // Restaurer le stock
        $stock->quantite += $bon->quantite;
        $stock->save();

        // Supprimer les enregistrements liés
        Vente::where('bl_id', $bon->id)->delete();
        Sortie::where('motif', 'LIKE', '%BL n°' . $bon->numBL . '%')->delete();
        ConsommationGasoil::where('bon_livraison_id', $bon->id)->delete();

        $bon->delete();

        return response()->json([
            'message' => 'BL supprimé avec succès, vente et sortie annulées, stock réajusté.'
        ]);
    }

    // 7️⃣ Récupérer les données pour les filtres
    public function filterData()
    {
        return response()->json([
            'clients' => Client::select('id', 'nom_client')->get(),
            'chauffeurs' => Conducteur::select('id', 'nom_conducteur')->get(),
            'vehicules' => Materiel::select('id', 'nom_materiel')->get(),
            'bonsCommandes' => Bon_commande::select('id', 'numero')->get(),
        ]);
    }

    // Valider un BL
    public function validerBl(Request $request, $id)
    {
        $request->validate([
            'heure_arrive' => 'required',
            'gasoil_arrive' => 'required|numeric|min:0',
            'date_arriver' => 'required|date',
            'distance' => 'nullable|numeric|min:0',
            'compteur_arrive' => 'nullable|numeric',
        ]);

        // Tout est exécuté dans une transaction
        return DB::transaction(function () use ($request, $id) {
            $bon = BonLivraison::with(['bonCommandeProduit.lieuStockage'])->findOrFail($id);

            if ($bon->isDelivred) {
                abort(400, 'Ce bon de livraison a déjà été validé.');
            }

            $vehicule = Materiel::with('pneus')->find($bon->vehicule_id);
            $destination = $bon->bonCommandeProduit->bonCommande->destination;

            // Récupérer le lieu de stockage
            $lieuStockage = $bon->bonCommandeProduit->lieuStockage;
            $heureChauffeur = $lieuStockage->heure_chauffeur ?? 0;

            // Calculs
            $heureMachine = $request->compteur_arrive - $bon->compteur_depart;
            $consommationCm = $bon->gasoil_depart - $request->gasoil_arrive;
            $consommationLitres = $vehicule->convertirCmEnLitres($consommationCm);

            $heureDepart = Carbon::parse($bon->heure_depart);
            $heureArrive = Carbon::parse($request->heure_arrive);
            $heuresMachine = $request->compteur_arrive - $bon->compteur_depart;
            $consommationReelleParHeure = $heuresMachine > 0 ? $consommationLitres / $heuresMachine : 0;

            $consommationHoraireReference = $vehicule->consommation_horaire ?? 0;
            $statutConsommationHoraire = 'normal';
            $ecartConsommationHoraire = 0;

            if ($consommationHoraireReference > 0) {
                $ecartConsommationHoraire = $consommationReelleParHeure - $consommationHoraireReference;
                $pourcentageEcartHoraire = ($ecartConsommationHoraire / $consommationHoraireReference) * 100;
                $statutConsommationHoraire = match (true) {
                    $pourcentageEcartHoraire > 15 => 'trop_elevee',
                    $pourcentageEcartHoraire < -15 => 'trop_basse',
                    default => 'normale',
                };
            }

            $consommationDestinationReference = $destination->consommation_reference ?? 0;
            $statutConsommationDestination = 'normal';
            $ecartConsommationDestination = 0;

            if ($consommationDestinationReference > 0) {
                $ecartConsommationDestination = $consommationLitres - $consommationDestinationReference;
                $pourcentageEcartDestination = ($ecartConsommationDestination / $consommationDestinationReference) * 100;
                $statutConsommationDestination = match (true) {
                    $pourcentageEcartDestination > 15 => 'trop_elevee',
                    $pourcentageEcartDestination < -15 => 'trop_basse',
                    default => 'normale',
                };
            }

            // Mise à jour du BL
            $bon->update([
                'heure_arrive' => $request->heure_arrive,
                'gasoil_arrive' => $request->gasoil_arrive,
                'date_arriver' => $request->date_arriver,
                'isDelivred' => 1,
                'compteur_arrive' => $request->compteur_arrive,
                'heure_machine' => $heureMachine,
                'heure_chauffeur' => $heureChauffeur,
                'consommation_totale' => $consommationLitres,
                'consommation_reelle_par_heure' => $consommationReelleParHeure,
                'consommation_horaire_reference' => $consommationHoraireReference,
                'ecart_consommation_horaire' => $ecartConsommationHoraire,
                'statut_consommation_horaire' => $statutConsommationHoraire,
                'consommation_destination_reference' => $consommationDestinationReference,
                'ecart_consommation_destination' => $ecartConsommationDestination,
                'statut_consommation_destination' => $statutConsommationDestination,
                'distance' => $request->distance ?? null,
            ]);

            // Mise à jour des pneus
            if ($vehicule && $vehicule->pneus) {
                foreach ($vehicule->pneus as $pneu) {
                    $pneu->increment('kilometrage', $request->distance ?? 0);
                }
            }

            // Mise à jour du véhicule
            if ($vehicule) {
                $vehicule->update([
                    'gasoil_consommation' => $vehicule->gasoil_consommation + $consommationLitres,
                    'actuelGasoil' => $request->gasoil_arrive,
                    'compteur_actuel' => $request->compteur_arrive ?? $vehicule->compteur_actuel,
                ]);
            }

            // Mise à jour de la vente et de la sortie
            $year = Carbon::parse($bon->date_livraison)->year;
            Vente::where('bl_id', $bon->id)->update([
                'observation' => 'Livraison client (BL n°' . $bon->numBL . '/' . $year . ') - VALIDÉ'
            ]);
            Sortie::where('motif', 'LIKE', '%BL n°' . $bon->numBL . '%')->update([
                'motif' => 'Livraison client (BL n°' . $bon->numBL . '/' . $year . ') - VALIDÉ'
            ]);

            // Création de la consommation
            ConsommationGasoil::create([
                'vehicule_id' => $bon->vehicule_id,
                'quantite' => $consommationLitres,
                'distance_km' => $request->distance ?? 0,
                'date_consommation' => $request->date_arriver ?? Carbon::now()->toDateString(),
                'consommation_reelle_par_heure' => $consommationReelleParHeure,
                'consommation_horaire_reference' => $consommationHoraireReference,
                'ecart_consommation_horaire' => $ecartConsommationHoraire,
                'statut_consommation_horaire' => $statutConsommationHoraire,
                'consommation_totale' => $consommationLitres,
                'consommation_destination_reference' => $consommationDestinationReference,
                'ecart_consommation_destination' => $ecartConsommationDestination,
                'statut_consommation_destination' => $statutConsommationDestination,
                'destination_id' => $destination->id ?? null,
                'bon_livraison_id' => $bon->id,
            ]);

            // Réponse JSON
            return response()->json([
                'message' => 'Bon de livraison validé avec succès !',
                'BL' => $bon->load([
                    'chauffeur',
                    'vehicule',
                    'aideChauffeur',
                    'bonCommandeProduit.bonCommande.client',
                    'bonCommandeProduit.bonCommande.destination',
                    'bonCommandeProduit.article',
                    'bonCommandeProduit.unite',
                    'bonCommandeProduit.lieuStockage'
                ]),
                'analyse_consommations' => [
                    'conversion' => [
                        'consommation_cm' => $consommationCm,
                        'capaciteL' => $vehicule->capaciteL,
                        'capaciteCm' => $vehicule->capaciteCm,
                        'consommation_litres' => round($consommationLitres, 2),
                    ],
                    'horaire' => [
                        'consommation_reelle_par_heure' => round($consommationReelleParHeure, 2),
                        'consommation_horaire_reference' => $consommationHoraireReference,
                        'ecart' => round($ecartConsommationHoraire, 2),
                        'statut' => $statutConsommationHoraire,
                        'heures_travail' => $heureMachine,
                    ],
                    'destination' => [
                        'consommation_totale_reelle' => $consommationLitres,
                        'consommation_destination_reference' => $consommationDestinationReference,
                        'ecart' => round($ecartConsommationDestination, 2),
                        'statut' => $statutConsommationDestination,
                        'destination' => $destination->nom_destination ?? 'Non spécifiée'
                    ]
                ]
            ]);
        });
    }

    // 9️⃣ API pour récupérer le prochain numéro BL pour une année
    public function getNextNumBLForYear(Request $request)
    {
        try {
            $year = $request->has('year')
                ? $request->year
                : ($request->has('date_livraison')
                    ? Carbon::parse($request->date_livraison)->year
                    : Carbon::now()->year);

            $nextNum = $this->getSuggestedNumBLForYear($year);

            return response()->json([
                'success' => true,
                'nextNumBL' => $nextNum,
                'year' => $year
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du numéro',
                'nextNumBL' => 1,
                'year' => Carbon::now()->year
            ]);
        }
    }

    // 🔟 Afficher un BL avec toutes ses données
    public function show($id)
    {
        $bon = BonLivraison::with([
            'chauffeur',
            'vehicule',
            'aideChauffeur',
            'bonCommandeProduit',
            'bonCommandeProduit.bonCommande',
            'bonCommandeProduit.bonCommande.client',
            'bonCommandeProduit.bonCommande.destination',
            'bonCommandeProduit.article',
            'bonCommandeProduit.unite',
            'bonCommandeProduit.lieuStockage',
            'consommationGasoil' // Ajouter la relation consommation
        ])->findOrFail($id);

        // Si le BL est validé, récupérer la distance depuis la consommation
        if ($bon->isDelivred && $bon->consommationGasoil) {
            $bon->distance = $bon->consommationGasoil->distance_km;
        }

        return response()->json($bon);
    }
}