<?php

namespace App\Http\Controllers\Produit;

use App\Exports\ProductionExport;
use App\Http\Controllers\Controller;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Stock;
use App\Models\AjustementStock\Entrer;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\ConsommationGasoil;
use App\Models\Parametre\CategorieArticle;
use App\Models\Parametre\Materiel;
use App\Models\Parametre\Unite;
use App\Models\Produit\Categorie;
use App\Models\Produit\ProductionMateriel;
use App\Models\Produit\ProductionProduit;
use App\Models\Produit\Produit;
use App\Models\User;
use App\Notifications\GasoilSeuilAtteint;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\ProductionService;

class ProduitController extends Controller
{
    public function index(Request $request)
    {
        $query = Produit::with([
            'produits.articleDepot.uniteLivraison',
            'produits.lieuStockage',
            'materiels.materiel',
            'materiels.categorieTravail',
            'userCreate',
            'userUpdate',
        ]);

        // Filtre par recherche (remarque, nom du produit)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('remarque', 'like', '%' . $search . '%')
                    ->orWhereHas('produits.articleDepot', function ($q2) use ($search) {
                        $q2->where('nom_article', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filtre par date de début (date_prod)
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('date_prod', '>=', $request->date_start);
        }

        // Filtre par date de fin (date_prod)
        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('date_prod', '<=', $request->date_end);
        }

        // Filtre par produit
        if ($request->has('produit_id') && !empty($request->produit_id)) {
            $query->whereHas('produits', function ($q) use ($request) {
                $q->where('produit_id', $request->produit_id);
            });
        }

        // Filtre par lieu de stockage
        if ($request->has('lieu_stockage_id') && !empty($request->lieu_stockage_id)) {
            $query->whereHas('produits', function ($q) use ($request) {
                $q->where('lieu_stockage_id', $request->lieu_stockage_id);
            });
        }

        // Tri par date de production décroissante
        $query->orderBy('date_prod', 'desc')->orderBy('heure_debut', 'desc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $produits = $query->paginate($perPage);

        return response()->json($produits);
    }

    public function create()
    {
        $unite = Unite::all();
        $categorieIds = CategorieArticle::where('nom_categorie', 'like', '%production%')->pluck('id');
        $produit = ArticleDepot::with(['uniteProduction', 'uniteLivraison'])
                                ->whereIn('categorie_id', $categorieIds)
                                ->get();
        $lieuStockage = Lieu_stockage::all();
        $materiels = Materiel::all();
        $categorieTravails = Categorie::all();

        return response()->json([
            'unite' => $unite,
            'produit' => $produit,
            'lieuStockage' => $lieuStockage,
            'materiels' => $materiels,
            'categorieTravails' => $categorieTravails,
        ]);
    }

    private function getUniteM3Id()
    {
        $unite = Unite::where('nom_unite', 'like', '%m³%')
            ->orWhere('nom_unite', 'like', '%m3%')
            ->orWhere('nom_unite', 'like', '%mètre cube%')
            ->first();

        return $unite ? $unite->id : 1;
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3])) {
            return response(['message' => 'Action non autorisée'], 403);
        }

        $request->validate([
            'date_prod'                       => 'required|date',
            'heure_debut'                     => 'required',
            'heure_fin'                       => 'required',
            'remarque'                        => 'nullable|string',

            'produits'                        => 'nullable|array',
            'produits.*.produit_id'           => 'required_with:produits|integer|exists:article_depots,id',
            'produits.*.quantite'             => 'required_with:produits|numeric|min:0.1',
            'produits.*.unite_id'             => 'required_with:produits|exists:unites,id',
            'produits.*.lieu_stockage_id'     => 'required_with:produits|exists:lieu_stockages,id',
            'produits.*.observation'          => 'nullable|string',

            'materiels'                       => 'required|array|min:1',
            'materiels.*.materiel_id'         => 'required|exists:materiels,id',
            'materiels.*.categorie_travail_id' => 'required|exists:categories,id',
            'materiels.*.heure_debut'         => 'required',
            'materiels.*.heure_fin'           => 'required',
            'materiels.*.compteur_debut'      => 'nullable|numeric',
            'materiels.*.compteur_fin'        => 'nullable|numeric',
            'materiels.*.gasoil_debut'        => 'required|numeric|min:0.1',
            'materiels.*.gasoil_fin'          => 'required|numeric|min:0',
            'materiels.*.observation'         => 'nullable|string',
        ]);

        $produits = collect($request->produits ?? [])->map(function ($p) use ($user) {
            return array_merge($p, ['user_name' => $user->nom ?? 'Système']);
        })->toArray();

        $production = app(ProductionService::class)->createProduction([
            'date_prod'      => $request->date_prod,
            'heure_debut'    => $request->heure_debut,
            'heure_fin'      => $request->heure_fin,
            'remarque'       => $request->remarque,
            'create_user_id' => $user->id,
            'update_user_id' => $user->id,
            'produits'       => $produits,
            'materiels'      => $request->materiels ?? [],
        ]);

        return response()->json([
            'message' => 'Production créée et validée avec succès !' .
                (!empty($produits) ? ' Le stock a été mis à jour.' : ''),
            'data' => $production,
        ], 201);
    }

    public function exportExcel(Request $request)
    {
        $filters = $request->all();
        return Excel::download(new ProductionExport($filters), 'Production.xlsx');
    }

    public function show($id)
    {
        $produit = Produit::with([
            'produits.uniteProduction',
            'produits.articleDepot.uniteProduction',
            'produits.lieuStockage',
            'materiels.materiel',
            'materiels.categorieTravail',
            'userCreate',
            'userUpdate',
        ])->find($id);

        if (!$produit) {
            return response()->json([
                'message' => 'Production non trouvée'
            ], 404);
        }

        return response()->json($produit);
    }

    public function edit($id)
    {
        $produit = Produit::with([
            'produits.uniteProduction',
            'produits.articleDepot.uniteProduction',
            'produits.lieuStockage',
            'materiels.materiel',
            'materiels.categorieTravail',
            'groupes.groupe',
            'groupes.categorieTravail',
        ])->find($id);

        if (!$produit) {
            return response()->json([
                'message' => 'Production non trouvée'
            ], 404);
        }

        return response()->json($produit);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::check() ? Auth::user() : null;
        if (!in_array($user->role_id, [1, 3])) {
            return response(['error' => 'Action non autorisée'], 403);
        }

        $request->validate([
            'date_prod' => 'required|date',
            'heure_debut' => 'required',
            'heure_fin' => 'required',
            'remarque' => 'nullable|string',

            // validation des produits - RENDU OPTIONNEL
            'produits' => 'nullable|array',
            'produits.*.produit_id' => 'required_with:produits|integer|exists:article_depots,id',
            'produits.*.quantite' => 'required_with:produits|numeric|min:0.1',
            'produits.*.unite_id' => 'required_with:produits|exists:unites,id',
            'produits.*.lieu_stockage_id' => 'required_with:produits|exists:lieu_stockages,id',
            'produits.*.observation' => 'nullable|string',

            // validation des matériels
            'materiels' => 'required|array|min:1',
            'materiels.*.materiel_id' => 'required|exists:materiels,id',
            'materiels.*.categorie_travail_id' => 'required|exists:categories,id',
            'materiels.*.heure_debut' => 'required',
            'materiels.*.heure_fin' => 'required',
            'materiels.*.compteur_debut' => 'nullable',
            'materiels.*.compteur_fin' => 'nullable',
            'materiels.*.gasoil_debut' => 'required|numeric|min:0.1',
            'materiels.*.gasoil_fin' => 'required|numeric|min:0',
            'materiels.*.observation' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $user, $id) {
            // Récupérer la production existante avec ses relations
            $production = Produit::with(['produits', 'materiels'])->find($id);

            if (!$production) {
                return response()->json([
                    'message' => 'Production non trouvée'
                ], 404);
            }

            // 1. Ajuster le stock: retirer les anciennes quantités (SEULEMENT SI ELLES EXISTENT)
            if ($production->produits && count($production->produits) > 0) {
                foreach ($production->produits as $oldProduit) {
                    $stock = Stock::where('article_id', $oldProduit->produit_id)
                        ->where('lieu_stockage_id', $oldProduit->lieu_stockage_id)
                        ->first();

                    if ($stock) {
                        $stock->quantite -= $oldProduit->quantite;
                        $stock->save();
                    }
                }
            }

            // 2. Supprimer les anciennes consommations de gasoil liées aux matériels
            foreach ($production->materiels as $materiel) {
                ConsommationGasoil::where('production_materiel_id', $materiel->id)->delete();
            }

            // 3. Supprimer les anciens produits et matériels
            $production->produits()->delete();
            $production->materiels()->delete();

            // 4. Mettre à jour la production principale
            $production->update([
                'date_prod' => $request->date_prod,
                'heure_debut' => $request->heure_debut,
                'heure_fin' => $request->heure_fin,
                'remarque' => $request->remarque,
                'update_user_id' => $user->id,
            ]);

            // 5. Ajouter les nouveaux produits (SEULEMENT S'ILS EXISTENT)
            if ($request->has('produits') && !empty($request->produits)) {
                foreach ($request->produits as $produitData) {
                    $unite = Unite::find($produitData['unite_id']);
                    $quantite = $produitData['quantite'];
                    $quantitePourStock = $quantite;
                    $uniteStockageId = $this->getUniteM3Id();

                    $productionProduit = ProductionProduit::create([
                        'production_id' => $production->id,
                        'produit_id' => $produitData['produit_id'],
                        'quantite' => $quantite,
                        'unite_id' => $produitData['unite_id'],
                        'lieu_stockage_id' => $produitData['lieu_stockage_id'],
                        'observation' => $produitData['observation'],
                        'unite_stockage_id' => $uniteStockageId,
                    ]);

                    // Mettre à jour le stock avec les nouvelles quantités
                    $stock = Stock::firstOrCreate(
                        [
                            'article_id' => $productionProduit->produit_id,
                            'lieu_stockage_id' => $productionProduit->lieu_stockage_id
                        ],
                        ['quantite' => 0]
                    );

                    $stock->quantite += $quantitePourStock;
                    $stock->save();

                    $article = ArticleDepot::with('categorie')->where('id', $produitData['produit_id'])->firstOrFail();
                    $categorie = $article->categorie_id;

                    // Mettre à jour ou créer l'entrée en stock
                    Entrer::updateOrCreate(
                        [
                            'motif' => 'Production n°' . $production->id,
                            'article_id' => $produitData['produit_id'],
                            'categorie_article_id' => $produitData[$categorie],
                        ],
                        [
                            'user_name' => $user->nom ?: 'Système',
                            'lieu_stockage_id' => $productionProduit->lieu_stockage_id,
                            'quantite' => $quantitePourStock,
                            'unite_id' => $uniteStockageId,
                            'entre' => now()->toDateString(),
                        ]
                    );
                }
            }

            // 6. Ajouter les nouveaux matériels avec calcul des consommations
            if ($request->has('materiels')) {
                foreach ($request->materiels as $materielData) {
                    // Calculs de consommation
                    $consommationCm = $materielData['gasoil_debut'] - $materielData['gasoil_fin'];
                    $vehicule = Materiel::find($materielData['materiel_id']);
                    $consommationTotale = $vehicule->convertirCmEnLitres($consommationCm);

                    // Calculs du temps de travail
                    $heureDebut = Carbon::parse($materielData['heure_debut']);
                    $heureFin = Carbon::parse($materielData['heure_fin']);
                    $heuresTravail = $heureDebut->diffInHours($heureFin);

                    $consommationReelleParHeure = $heuresTravail > 0
                        ? $consommationTotale / $heuresTravail
                        : 0;

                    // COMPARAISON AVEC CONSOMMATION HORAIRE DU VEHICULE
                    $consommationHoraireReference = $vehicule->consommation_horaire;
                    $statutConsommationHoraire = 'normal';
                    $ecartConsommationHoraire = 0;

                    if ($consommationHoraireReference > 0) {
                        $ecartConsommationHoraire = $consommationReelleParHeure - $consommationHoraireReference;
                        $pourcentageEcartHoraire = ($ecartConsommationHoraire / $consommationHoraireReference) * 100;

                        if ($pourcentageEcartHoraire > 15) {
                            $statutConsommationHoraire = 'trop_elevee';
                        } elseif ($pourcentageEcartHoraire < -15) {
                            $statutConsommationHoraire = 'trop_basse';
                        } else {
                            $statutConsommationHoraire = 'normale';
                        }
                    }

                    $productionMateriel = ProductionMateriel::create([
                        'production_id' => $production->id,
                        'materiel_id' => $materielData['materiel_id'],
                        'categorie_travail_id' => $materielData['categorie_travail_id'],
                        'heure_debut' => $materielData['heure_debut'],
                        'heure_fin' => $materielData['heure_fin'],
                        'compteur_debut' => $materielData['compteur_debut'],
                        'compteur_fin' => $materielData['compteur_fin'],
                        'gasoil_debut' => $materielData['gasoil_debut'],
                        'gasoil_fin' => $materielData['gasoil_fin'],
                        'observation' => $materielData['observation'],
                        // Données de consommation
                        'consommation_reelle_par_heure' => $consommationReelleParHeure,
                        'consommation_horaire_reference' => $consommationHoraireReference,
                        'ecart_consommation_horaire' => $ecartConsommationHoraire,
                        'statut_consommation_horaire' => $statutConsommationHoraire,
                        'consommation_totale' => $consommationTotale,
                    ]);

                    // Mettre à jour le gasoil des véhicules
                    $vehicule = Materiel::find($materielData['materiel_id']);
                    if ($vehicule) {
                        $currentConsommation = $vehicule->gasoil_consommation ?? 0;

                        $vehicule->update([
                            'gasoil_consommation' => $currentConsommation + $consommationTotale,
                            'actuelGasoil' => $materielData['gasoil_fin'],
                            'compteur_actuel' => $materielData['compteur_fin'],
                        ]);
                    }

                    // Enregistrer la nouvelle consommation
                    ConsommationGasoil::create([
                        'vehicule_id' => $materielData['materiel_id'],
                        'quantite' => $consommationTotale,
                        'distance_km' => $materielData['compteur_debut'] ?
                            ($materielData['compteur_fin'] - $materielData['compteur_debut']) : 0,
                        'date_consommation' => Carbon::now()->toDateString(),
                        'consommation_reelle_par_heure' => $consommationReelleParHeure,
                        'consommation_horaire_reference' => $consommationHoraireReference,
                        'ecart_consommation_horaire' => $ecartConsommationHoraire,
                        'statut_consommation_horaire' => $statutConsommationHoraire,
                        'consommation_totale' => $consommationTotale,
                        'bon_livraison_id' => null,
                        'transfert_produit_id' => null,
                        'production_materiel_id' => $productionMateriel->id,
                    ]);
                }
            }

            // Chargement des relations
            $production->load([
                'produits.uniteProduction',
                'produits.articleDepot.uniteProduction',
                'produits.lieuStockage',
                'materiels.materiel',
                'materiels.categorieTravail',
            ]);

            return response()->json([
                'message' => 'Production mise à jour avec succès !',
                'data' => $production
            ], 201);
        });
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3])) {
            return response(['message' => 'Action non autorisée'], 403);
        }

        $production = Produit::with(['produits', 'materiels'])->find($id);

        if (!$production) {
            return response()->json([
                'message' => 'Production non trouvée'
            ], 404);
        }

        return DB::transaction(function () use ($production, $user) {
            // Ajuster le stock : retirer les quantités produites (SEULEMENT SI ELLES EXISTENT)
            if ($production->produits && count($production->produits) > 0) {
                foreach ($production->produits as $produit) {
                    $stock = Stock::where('article_id', $produit->produit_id)
                        ->where('lieu_stockage_id', $produit->lieu_stockage_id)
                        ->first();

                    if ($stock) {
                        $stock->quantite -= $produit->quantite;
                        $stock->save();
                    }
                }
            }

            // Supprimer les consommations de gasoil liées aux matériels
            foreach ($production->materiels as $materiel) {
                ConsommationGasoil::where('production_materiel_id', $materiel->id)->delete();
            }

            // Supprimer la production (les relations seront supprimées par CASCADE si bien configurées)
            $production->delete();

            return response()->json([
                'message' => 'Production supprimée avec succès !'
            ]);
        });
    }

    /**
     * Récupérer les données pour les filtres
     */
    public function filterData()
    {
        return response()->json([
            'produits' => ArticleDepot::select('id', 'nom_article')->get(),
            'lieuStockages' => Lieu_stockage::select('id', 'nom_lieu')->get(),
            'materiels' => Materiel::select('id', 'nom_materiel')->get(),
            'categorieTravails' => Categorie::select('id', 'nom_categorie')->get(),
        ]);
    }

    /**
     * Statistiques de production pour le dashboard
     */
    public function stats(Request $request)
    {
        $query = Produit::query();

        // Filtres par date
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('date_prod', '>=', $request->date_start);
        }

        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('date_prod', '<=', $request->date_end);
        }

        // Compter le nombre de productions
        $totalProductions = $query->count();

        // Calculer la quantité totale via la relation produits (en m³)
        $quantiteTotale = DB::table('production_produits')
            ->join('produits', 'production_produits.production_id', '=', 'produits.id')
            ->when($request->has('date_start') && !empty($request->date_start), function ($q) use ($request) {
                $q->whereDate('produits.date_prod', '>=', $request->date_start);
            })
            ->when($request->has('date_end') && !empty($request->date_end), function ($q) use ($request) {
                $q->whereDate('produits.date_prod', '<=', $request->date_end);
            })
            ->sum('production_produits.quantite');

        // Compter les produits différents
        $produitsDifferents = DB::table('production_produits')
            ->join('produits', 'production_produits.production_id', '=', 'produits.id')
            ->when($request->has('date_start') && !empty($request->date_start), function ($q) use ($request) {
                $q->whereDate('produits.date_prod', '>=', $request->date_start);
            })
            ->when($request->has('date_end') && !empty($request->date_end), function ($q) use ($request) {
                $q->whereDate('produits.date_prod', '<=', $request->date_end);
            })
            ->distinct('production_produits.produit_id')
            ->count('production_produits.produit_id');

        // Productions par jour (pour graphique)
        $productionsParJour = DB::table('produits')
            ->select(DB::raw('DATE(date_prod) as date'), DB::raw('COUNT(*) as count'))
            ->when($request->has('date_start') && !empty($request->date_start), function ($q) use ($request) {
                $q->whereDate('date_prod', '>=', $request->date_start);
            })
            ->when($request->has('date_end') && !empty($request->date_end), function ($q) use ($request) {
                $q->whereDate('date_prod', '<=', $request->date_end);
            })
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'total_productions' => $totalProductions,
            'quantite_totale' => $quantiteTotale,
            'produits_differents' => $produitsDifferents,
            'productions_par_jour' => $productionsParJour,
        ]);
    }

    public function getProductionSummary(Request $request)
    {
        $period = $request->input('period', '7days');

        // Total production via la relation (en m³)
        $totalProductionQuery = DB::table('production_produits')
            ->join('produits', 'production_produits.production_id', '=', 'produits.id');

        $countProduitsQuery = DB::table('production_produits')
            ->join('produits', 'production_produits.production_id', '=', 'produits.id');

        if ($period === '7days') {
            $totalProductionQuery->where('produits.date_prod', '>=', now()->subDays(7));
            $countProduitsQuery->where('produits.date_prod', '>=', now()->subDays(7));
        } elseif ($period === 'last_week') {
            $totalProductionQuery->whereBetween('produits.date_prod', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek()
            ]);
            $countProduitsQuery->whereBetween('produits.date_prod', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek()
            ]);
        } elseif ($period === 'this_month') {
            $totalProductionQuery->whereBetween('produits.date_prod', [
                now()->startOfMonth(),
                now()->endOfMonth()
            ]);
            $countProduitsQuery->whereBetween('produits.date_prod', [
                now()->startOfMonth(),
                now()->endOfMonth()
            ]);
        }

        $totalProduction = $totalProductionQuery->sum('production_produits.quantite');
        $countProduits = $countProduitsQuery->distinct('production_produits.produit_id')
            ->count('production_produits.produit_id');

        return response()->json([
            'total_production' => $totalProduction,
            'nombre_produits' => $countProduits,
        ]);
    }

    public function getProductionByProduct(Request $request)
    {
        $period = $request->input('period', '7days');

        $query = DB::table('production_produits')
            ->join('produits', 'production_produits.production_id', '=', 'produits.id')
            ->join('article_depots', 'production_produits.produit_id', '=', 'article_depots.id')
            ->selectRaw('production_produits.produit_id, article_depots.nom_article, SUM(production_produits.quantite) as total')
            ->groupBy('production_produits.produit_id', 'article_depots.nom_article');

        if ($period === '7days') {
            $query->where('produits.date_prod', '>=', now()->subDays(7));
        } elseif ($period === 'last_week') {
            $query->whereBetween('produits.date_prod', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek()
            ]);
        } elseif ($period === 'this_month') {
            $query->whereBetween('produits.date_prod', [
                now()->startOfMonth(),
                now()->endOfMonth()
            ]);
        }

        $data = $query->get()->map(function ($item) {
            return [
                'name' => $item->nom_article ?? 'Inconnu',
                'production' => (float) $item->total,
            ];
        });

        return response()->json($data);
    }

    /**
     * Récupère les derniers enregistrements de production
     * Optionnel : filtrable par période
     */
    public function latest(Request $request)
    {
        $period = $request->input('period', '7days');

        $query = Produit::with([
            'produits.articleDepot',
            'produits.lieuStockage',
            'produits.articleDepot.uniteLivraison',
        ]);

        if ($period === '7days') {
            $query->where('date_prod', '>=', now()->subDays(7));
        } elseif ($period === 'last_week') {
            $query->whereBetween('date_prod', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek()
            ]);
        } elseif ($period === 'this_month') {
            $query->whereBetween('date_prod', [
                now()->startOfMonth(),
                now()->endOfMonth()
            ]);
        }

        $productions = $query->orderBy('date_prod', 'desc')
            ->orderBy('heure_debut', 'desc')
            ->limit(50)
            ->get();

        // Formater les données pour le front
        $result = [];
        foreach ($productions as $production) {
            // Si la production a des produits
            if ($production->produits && count($production->produits) > 0) {
                foreach ($production->produits as $produit) {
                    $result[] = [
                        'id' => $production->id,
                        'date_prod' => $production->date_prod,
                        'heure_debut' => $production->heure_debut,
                        'produit_name' => $produit->articleDepot->nom_article ?? 'Inconnu',
                        'lieuStockage' => $produit->lieuStockage->nom_lieu ?? 'Inconnu',
                        'quantite' => $produit->quantite,
                        'unite_production' => $produit->uniteProduction->nom_unite,
                        'unite_stockage' => $produit->uniteStockage->nom_unite ?? 'm³',
                        'observation' => $produit->observation ?? '-',
                    ];
                }
            } else {
                // Si pas de produits
                $result[] = [
                    'id' => $production->id,
                    'date_prod' => $production->date_prod,
                    'heure_debut' => $production->heure_debut,
                    'produit_name' => 'Aucun produit',
                    'lieuStockage' => '—',
                    'quantite' => 0,
                    'unite_production' => '—',
                    'unite_stockage' => '—',
                    'observation' => $production->remarque ?? '-',
                ];
            }
        }

        return response()->json($result);
    }

    /**
     * Statistiques avancées pour le dashboard
     */
    public function getDashboardStats(Request $request)
    {
        $period = $request->input('period', '7days');

        // Productions totales
        $productionsQuery = Produit::query();
        $productionsParProduitQuery = DB::table('production_produits')
            ->join('produits', 'production_produits.production_id', '=', 'produits.id')
            ->join('article_depots', 'production_produits.produit_id', '=', 'article_depots.id');

        if ($period === '7days') {
            $productionsQuery->where('date_prod', '>=', now()->subDays(7));
            $productionsParProduitQuery->where('produits.date_prod', '>=', now()->subDays(7));
        } elseif ($period === 'last_week') {
            $productionsQuery->whereBetween('date_prod', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek()
            ]);
            $productionsParProduitQuery->whereBetween('produits.date_prod', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek()
            ]);
        } elseif ($period === 'this_month') {
            $productionsQuery->whereBetween('date_prod', [
                now()->startOfMonth(),
                now()->endOfMonth()
            ]);
            $productionsParProduitQuery->whereBetween('produits.date_prod', [
                now()->startOfMonth(),
                now()->endOfMonth()
            ]);
        }

        $totalProductions = $productionsQuery->count();
        $quantiteTotale = $productionsParProduitQuery->sum('production_produits.quantite');

        // Top 5 des produits les plus produits (en m³)
        $topProduits = $productionsParProduitQuery
            ->selectRaw('article_depots.nom_article, SUM(production_produits.quantite) as total')
            ->groupBy('article_depots.nom_article')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Productions par jour (pour graphique)
        $productionsParJour = DB::table('produits')
            ->select(DB::raw('DATE(date_prod) as date'), DB::raw('COUNT(*) as count'))
            ->when($period === '7days', function ($q) {
                $q->where('date_prod', '>=', now()->subDays(7));
            })
            ->when($period === 'last_week', function ($q) {
                $q->whereBetween('date_prod', [
                    now()->subWeek()->startOfWeek(),
                    now()->subWeek()->endOfWeek()
                ]);
            })
            ->when($period === 'this_month', function ($q) {
                $q->whereBetween('date_prod', [
                    now()->startOfMonth(),
                    now()->endOfMonth()
                ]);
            })
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'total_productions' => $totalProductions,
            'quantite_totale' => $quantiteTotale,
            'top_produits' => $topProduits,
            'productions_par_jour' => $productionsParJour,
        ]);
    }
}
