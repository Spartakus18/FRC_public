<?php

namespace App\Http\Controllers\Consommable;

use App\Exports\HuilesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consommable\HuileRequest;
use App\Http\Requests\TransfertHuileRequest;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Sortie;
use App\Models\AjustementStock\Stock;
use App\Models\BC\BonHuile;
use App\Models\Consommable\Huile;
use App\Models\Parametre\CategorieArticle;
use App\Models\Parametre\Materiel;
use App\Models\Parametre\Unite;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class HuileController extends Controller
{
    /**
     * Liste des huiles
     */
    public function index(Request $request)
    {
        $query = Huile::with([
            'materielCible',
            'subdivisionCible',
            'materielSource',
            'subdivisionSource',
            'articleDepot',
            'sourceLieuStockage',
            'bon'
        ]);

        // Filtre par recherche (nom du matériel)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('materielCible', function ($q) use ($search) {
                $q->where('nom_materiel', 'like', '%' . $search . '%');
            });
        }

        // Filtre par date de début
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('created_at', '>=', $request->date_start);
        }

        // Filtre par date de fin
        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('created_at', '<=', $request->date_end);
        }

        // Tri par date de création décroissante
        $query->orderBy('created_at', 'desc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $huiles = $query->paginate($perPage);

        // Transformer les données pour inclure les informations de stock
        /** @var \Illuminate\Pagination\LengthAwarePaginator $huiles */
        $huiles->getCollection()->transform(function ($huile) {
            $data = $huile->toArray();

            // Nom du lieu de stockage source
            $data['nom_lieu_stockage_source'] = $huile->sourceLieuStockage->nom ?? null;

            // Quantité avant et après l'ajout (si source est un lieu de stockage)
            if ($huile->source_lieu_stockage_id) {
                $stockActuel = Stock::where('article_id', $huile->article_versement_id)
                    ->where('lieu_stockage_id', $huile->source_lieu_stockage_id)
                    ->first();

                if ($stockActuel) {
                    // Quantité après = stock actuel (déjà déduit)
                    $data['quantite_apres_ajout'] = (float) $stockActuel->quantite;
                    // Quantité avant = stock actuel + quantité prélevée
                    $data['quantite_avant_ajout'] = (float) $stockActuel->quantite + (float) $huile->quantite;
                } else {
                    $data['quantite_avant_ajout'] = null;
                    $data['quantite_apres_ajout'] = null;
                }
            } else {
                // Si source n'est pas un lieu de stockage (station ou autre)
                $data['quantite_avant_ajout'] = null;
                $data['quantite_apres_ajout'] = null;
            }

            return $data;
        });

        // Vérifier si l'utilisateur est un administrateur
        $user = auth()->user();
        $isAdmin = $user && $user->role_id === 1;
        $isSupervisor = $user && $user->role_id === 3;

        if (!$isAdmin && !$isSupervisor) {
            // Transformer manuellement les éléments pour cacher les champs sensibles
            /** @var \Illuminate\Pagination\LengthAwarePaginator $huiles */
            $huiles->getCollection()->transform(function ($huile) {
                return array_merge(
                    $huile,
                    [
                        'prix_total' => null,
                        'quantite' => null,
                        'quantite_avant_ajout' => null, // Cacher aussi pour les non-admins
                        'quantite_apres_ajout' => null  // Cacher aussi pour les non-admins
                    ]
                );
            });
        }

        return response()->json($huiles);
    }

    public function exportExcel(Request $request)
    {
        try {
            return Excel::download(new HuilesExport($request->all()), 'huiles.xlsx');
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
    }

    /**
     * Confirmer le versement d'une huile
     *
     * @param Huile $huile
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Huile $huile)
    {
        DB::beginTransaction();

        try {
            // Vérifier si l'huile a déjà été consommée
            if ($huile->is_consumed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette huile a déjà été versée'
                ], 409);
            }

            // Vérifier la source de l'huile (lieu de stockage)
            if (!$huile->source_lieu_stockage_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lieu de stockage source non spécifié'
                ], 400);
            }

            // Vérifier l'article de versement
            if (!$huile->article_versement_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Article de versement non spécifié'
                ], 400);
            }

            // Vérifier et mettre à jour le stock source
            $stock = Stock::where('lieu_stockage_id', $huile->source_lieu_stockage_id)
                ->where('article_id', $huile->article_versement_id)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                // Créer un enregistrement de stock s'il n'existe pas (avec quantité 0)
                $stock = Stock::create([
                    'lieu_stockage_id' => $huile->source_lieu_stockage_id,
                    'article_id' => $huile->article_versement_id,
                    'quantite' => 0,
                ]);
            }

            // Enregistrer la quantité AVANT
            $quantiteAvant = (float) $stock->quantite;
            // Récupérer la quantité en litres
            $quantiteLitres = (float) $huile->quantite;

            // Vérifier si le stock est suffisant
            if ($stock->quantite < $quantiteLitres) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock source insuffisant. Disponible: ' . $stock->quantite . ' L, Demandé: ' . $quantiteLitres . ' L'
                ], 422);
            }

            // Calculer la quantité APRÈS
            $quantiteApres = $quantiteAvant - $quantiteLitres;

            // Mettre à jour le stock
            $stock->quantite = $quantiteApres;

            // Empêcher les quantités négatives
            if ($stock->quantite < 0) {
                throw new Exception('Quantité négative dans le stock après déduction');
            }

            $stock->save();



            // --- AJOUT: Création d'une entrée dans la table Sortie ---
            // Récupérer le bon d'huile pour le numéro de bon
            $bonHuile = $huile->bon;
            if (!$bonHuile) {
                // Charger la relation si elle n'est pas déjà chargée
                $bonHuile = BonHuile::find($huile->bon_id);
            }

            // Récupérer l'unité "L" (litre)
            $uniteLitre = Unite::whereIn(
                DB::raw('LOWER(nom_unite)'),
                ['l', 'litre']
            )->first();
            if (!$uniteLitre) {
                throw new Exception('Unité "L" (litre) non trouvée dans la base de données');
            }

            // Créer l'enregistrement de sortie
            $sortie = Sortie::create([
                'user_name' => auth()->user()->name ?? auth()->user()->nom ?? 'System',
                'article_id' => $huile->article_versement_id,
                'categorie_article_id' => ArticleDepot::where('id', $huile->article_versement_id)->value('categorie_id'),
                'lieu_stockage_id' => $huile->source_lieu_stockage_id,
                'quantite' => $quantiteLitres,
                'unite_id' => $uniteLitre->id,
                'motif' => "Versement d'huile - Bon n° " . ($bonHuile ? $bonHuile->num_bon : 'N/A'),
                'sortie' => now()->toDateString(),
            ]);

            // --- ENREGISTRER LES QUANTITÉS DANS L'HISTORIQUE ---
            $huile->quantite_stock_avant = $quantiteAvant;
            $huile->quantite_stock_apres = $quantiteApres;
            $huile->is_consumed = true;
            $huile->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Versement d\'huile confirmé avec succès'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Erreur confirmation versement huile', [
                'huile_id' => $huile->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation du versement d\'huile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les détails d'une huile pour confirmation
     *
     * @param Huile $huile
     * @return \Illuminate\Http\JsonResponse
     */
    public function showForConfirmation(Huile $huile)
    {
        try {
            $huile->load([
                'bon',
                'sourceLieuStockage',
                'articleDepot',
                'materielCible',
                'subdivisionCible'
            ]);

            // Récupérer le stock actuel pour cet article dans ce lieu
            $stockActuel = Stock::where('lieu_stockage_id', $huile->source_lieu_stockage_id)
                ->where('article_id', $huile->article_versement_id)
                ->sum('quantite');

            return response()->json([
                'success' => true,
                'data' => [
                    'huile' => $huile,
                    'stock_actuel' => $stockActuel,
                    'suffisant' => $stockActuel >= $huile->quantite
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des détails',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Création operaton d'huile
     */
    public function store(HuileRequest $request)
    {
        $data = $request->validated();
        $data['ajouter_par'] = auth()->user()->nom;
        $bonHuile = BonHuile::where('id', $data['bon_id'])->firstOrFail();

        // --- 1. PRÉPARATION DES DONNÉES ---
        if (!isset($data['source'])) {
            $data['source_station'] = null;
            $data['source_lieu_stockage_id'] = null;
        } elseif ($data['source'] === 'station' || $data['source'] === 'autre') {
            $data['source_station'] = $data['source'];
            $data['source_lieu_stockage_id'] = null;
        } else {
            $data['source_lieu_stockage_id'] = $data['source'];
            $data['source_station'] = null;
        }
        unset($data['source']);

        $huile = null; // Initialiser pour la portée (scope)

        // --- 2. BLOC DE TRANSACTION (Écritures) ---
        try {
            DB::transaction(function () use ($data, &$huile, $bonHuile) {

                // Opération 1: Créer l'historique Huile
                $huile = Huile::create($data);

                // Opération 2: Gérer le stock (si la source est un lieu de stockage)
                if ($data['source_station'] === null && $data['source_lieu_stockage_id'] !== null) {

                    // Récupérer l'article
                    $articleHuile = ArticleDepot::find($data['article_versement_id']);
                    if (!$articleHuile) {
                        // Au lieu de return, on "throw" pour annuler la transaction
                        throw new \Exception('Article huile non trouvé', 422); // 422 = Unprocessable Entity
                    }

                    // Récupérer le stock
                    $stock = Stock::where('article_id', $articleHuile->id)
                        ->where('lieu_stockage_id', $data['source_lieu_stockage_id'])
                        ->first();

                    if (!$stock) {
                        throw new \Exception('Stock non trouvé pour cet article et ce lieu', 422);
                    }

                    // Vérifier la quantité
                    if ($stock->quantite < $data['quantite']) {
                        throw new \Exception('Stock insuffisant. Stock disponible: ' . $stock->quantite . ' L', 422);
                    }

                    // Mettre à jour le stock
                    $stock->quantite -= $data['quantite'];
                    $stock->save(); // Si échec -> ROLLBACK

                    // Opération 3: Créer la sortie
                    Sortie::create([
                        'user_name' => auth()->user()->nom,
                        'article_id' => $articleHuile->id,
                        'lieu_stockage_id' => $data['source_lieu_stockage_id'],
                        'quantite' => $data['quantite'],
                        'unite_id' => Unite::where('nom_unite', 'L')->value('id'),
                        'motif' => "Versement d'huile - Bon n° " . $bonHuile->num_bon,
                        'sortie' => now()->toDateString(),
                    ]); // Si échec -> ROLLBACK
                }

                // Opération 4: Gérer le Bon Huile
                // firstOrFail() lèvera une ModelNotFoundException si non trouvé -> ROLLBACK

                if ($bonHuile->is_consumed) {
                    throw new \Exception("Ce bon d'huile a déjà été utilisé", 409); // 409 = Conflit
                }

                $bonHuile->update(['is_consumed' => true]); // Si échec -> ROLLBACK

            }); // --- FIN DE LA TRANSACTION (COMMIT) ---

            // --- 3. SUCCÈS ---
            // N'est exécuté que si la transaction a réussi
            $huile->load(['materielCible', 'subdivisionCible', 'articleDepot']);
            return response()->json([
                'message' => 'Huile ajoutée avec succès',
                'data' => $huile,
            ]);
        } catch (ModelNotFoundException $e) {
            // Catch spécifique pour le bon_huile->firstOrFail()
            return response()->json([
                'message' => 'Bon d\'huile non trouvé'
            ], 404);
        } catch (Throwable $e) {
            // Catch générique pour TOUTES les autres erreurs
            // (Stock insuffisant, Article non trouvé, Erreur SQL, etc.)

            $code = $e->getCode();

            // S'assurer que le code est un code d'erreur HTTP valide
            if (!is_int($code) || $code < 400 || $code >= 600) {
                $code = 500; // Erreur serveur interne par défaut
            }

            return response()->json([
                'message' => $e->getMessage() // Renvoie le message (ex: "Stock insuffisant...")
            ], $code);
        }
    }

    /**
     * Transfert d'huile entre matériels
     */
    public function transfert(TransfertHuileRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();
            $user = auth()->user();

            // --- 1. VÉRIFICATION DES MATÉRIELS ---
            $materielSource = Materiel::find($data['materiel_id_source']);
            $materielCible = Materiel::find($data['materiel_id_cible']);

            if (!$materielSource) {
                throw new ModelNotFoundException('Matériel source non trouvé');
            }
            if (!$materielCible) {
                throw new ModelNotFoundException('Matériel cible non trouvé');
            }

            // --- 3. CRÉATION DU BON D'HUILE ---
            $bonHuile = BonHuile::create([
                'num_bon' => $data['num_bon'],
                'ajouter_par' => $user->nom,
            ]);

            // Sauvegarder les modifications
            $materielSource->save();
            $materielCible->save();

            $quantite = (float) $data['quantite'];

            // --- 5. CRÉATION DE L'HUILE ---
            $huileData = [
                'bon_id' => $bonHuile->id,
                'quantite' => $quantite,
                'type_operation' => 'transfert',
                'materiel_id_source' => $data['materiel_id_source'],
                'materiel_id_cible' => $data['materiel_id_cible'],
                'subdivision_id_source' => $data['subdivision_id_source'] ?? null,
                'subdivision_id_cible' => $data['subdivision_id_cible'] ?? null,
                'article_versement_id' => $data['article_versement_id'],
                'ajouter_par' => $user->nom,
                'is_consumed' => true,
            ];

            $huile = Huile::create($huileData);

            // --- 6. CHARGEMENT DES RELATIONS POUR LA RÉPONSE ---
            $huile->load(['materielSource', 'materielCible', 'bon', 'articleDepot']);
            $bonHuile->load(['huile']);

            DB::commit();

            return response()->json([
                'message' => 'Transfert d\'huile effectué avec succès',
                'data' => [
                    'bon' => $bonHuile,
                    'huile' => $huile,
                    'materiel_source' => $materielSource->only(['id', 'nom_materiel', 'actuelHuile']),
                    'materiel_cible' => $materielCible->only(['id', 'nom_materiel', 'actuelHuile']),
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors du transfert d\'huile', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'user_id' => auth()->id()
            ]);

            $code = $e->getCode();
            if (!is_int($code) || $code < 400 || $code >= 600) {
                $code = 500;
            }

            return response()->json(['message' => $e->getMessage()], $code);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Erreur fatale lors du transfert d\'huile', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['message' => 'Erreur interne du serveur'], 500);
        }
    }

    /**
     * Mise à jour d'huile
     */
    public function update(HuileRequest $request, Huile $huile)
    {
        $data = $request->validated();
        $data['modifier_par'] = auth()->user()->nom;

        // Transformation de la source
        if ($data['source'] === 'station' || $data['source'] === 'autre') {
            if ($data['source'] === 'station') {
                $data['source_station'] = 'station';
                $data['source_lieu_stockage_id'] = null;
            } else {
                $data['source_station'] = 'autre';
                $data['source_lieu_stockage_id'] = null;
            }
        } else {
            $data['source_lieu_stockage_id'] = $data['source'];
            $data['source_station'] = null;
        }

        unset($data['source']);

        $huile->update($data);
        $huile->load(['materielCible', 'subdivisionCible', 'articleDepot']);

        return response()->json([
            'message' => 'Huile modifiée avec succès',
            'data' => $huile
        ]);
    }

    public function getArticlesHuile()
    {
        // Trouver la catégorie "huile"
        $categorieHuile = CategorieArticle::where('nom_categorie', 'like', '%huile%')->first();

        if (!$categorieHuile) {
            return response()->json([]);
        }

        // Récupérer les articles de cette catégorie avec leurs relations
        $articles = ArticleDepot::with(['unite', 'categorie'])
            ->where('categorie_id', $categorieHuile->id)
            ->get()
            ->map(function ($article) {
                return [
                    'id' => $article->id,
                    'nom_article' => $article->nom_article,
                    'unite' => $article->unite->nom_unite ?? 'N/A',
                    'categorie' => $article->categorie->nom_categorie ?? 'N/A',
                    'created_at' => $article->created_at,
                    'updated_at' => $article->updated_at,
                ];
            });

        return response()->json($articles);
    }

    public function destroy(Huile $huile)
    {
        if (!$huile) {
            return response()->json([
                "message" => "Huile non trouvée"
            ], 500);
        }

        $huile->delete();

        return response()->json([
            "message" => "Huile supprimé avec succès"
        ]);
    }
}
