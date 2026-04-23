<?php

namespace App\Http\Controllers\AjustementStock;

use App\Http\Controllers\Controller;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Entrer;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\AjustementStock\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EntrerController extends Controller
{
    /**
     * Display a listing of the resource (Gas-oil).
     */
    public function indexGasoil(Request $request)
    {
        return $this->indexByCategorie($request, 1);
    }

    /**
     * Display a listing of the resource (Huile).
     */
    public function indexHuile(Request $request)
    {
        return $this->indexByCategorie($request, 2);
    }

    /**
     * Display a listing of the resource (Produit).
     */
    public function indexProduit(Request $request)
    {
        return $this->indexByCategorie($request, 3);
    }

    /**
     * Méthode privée pour filtrer l'index par catégorie.
     */
    private function indexByCategorie(Request $request, $categorieId)
    {
        $query = Entrer::with(['article', 'article.uniteLivraison', 'article.categorie', 'lieuStockage', 'unite'])
            ->where('categorie_article_id', $categorieId);

        // Filtre par recherche
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('motif', 'like', '%' . $search . '%')
                    ->orWhere('user_name', 'like', '%' . $search . '%')
                    ->orWhereHas('article', function ($q2) use ($search) {
                        $q2->where('nom_article', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('lieuStockage', function ($q3) use ($search) {
                        $q3->where('nom', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filtre par date de début
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('entre', '>=', $request->date_start);
        }

        // Filtre par date de fin
        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('entre', '<=', $request->date_end);
        }

        // Tri par défaut (du plus récent au plus ancien)
        $query->orderBy('entre', 'desc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $entrees = $query->paginate($perPage);

        return response()->json($entrees);
    }

    /**
     * Show the form for creating a new resource (toutes catégories).
     */
    public function create()
    {
        $article = ArticleDepot::with('uniteLivraison')->get();
        $lieu = Lieu_stockage::all();
        $unite = \App\Models\Parametre\Unite::all();

        return response()->json([
            'article' => $article,
            'lieu' => $lieu,
            'unite' => $unite,
        ]);
    }

    /**
     * Show the form for creating a new resource (Gas-oil).
     */
    public function createGasoil()
    {
        return $this->createByCategorie(1);
    }

    /**
     * Show the form for creating a new resource (Huile).
     */
    public function createHuile()
    {
        return $this->createByCategorie(2);
    }

    /**
     * Show the form for creating a new resource (Produit).
     */
    public function createProduit()
    {
        return $this->createByCategorie(3);
    }

    /**
     * Méthode privée pour filtrer les articles par catégorie pour la création.
     */
    private function createByCategorie($categorieId)
    {
        $article = ArticleDepot::with('uniteLivraison')
            ->where('categorie_id', $categorieId)
            ->get();
        $lieu = Lieu_stockage::all();
        $unite = \App\Models\Parametre\Unite::all();

        return response()->json([
            'article' => $article,
            'lieu' => $lieu,
            'unite' => $unite,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validation du tableau
        $request->validate([
            'articles' => 'required|array|min:1',
            'articles.*.article_id' => 'required|exists:article_depots,id',
            'articles.*.lieu_stockage_id' => 'required|exists:lieu_stockages,id',
            'articles.*.quantite' => 'required|numeric',
            'articles.*.prix_unitaire' => 'nullable|numeric|min:0',
            'articles.*.prix_total' => 'nullable|numeric',
            'articles.*.motif' => 'nullable|string|max:255',
            'articles.*.entre' => 'nullable|date'
        ]);

        $userName = Auth::user()->nom ?? 'system';

        DB::transaction(function () use ($request, $userName) {
            foreach ($request->articles as $article) {
                Log::info("Création entrée pour article: ", $article);

                // Récupérer l'article pour obtenir son unité de livraison
                $articleDepot = ArticleDepot::with('uniteLivraison', 'categorie')->find($article['article_id']);

                if (!$articleDepot || !$articleDepot->uniteLivraison) {
                    throw new \Exception("Article non trouvé ou sans unité de livraison définie");
                }

                // Utiliser l'unité de livraison de l'article
                $uniteLivraison = $articleDepot->uniteLivraison;
                $categorieArticle = $articleDepot->categorie;

                // Conserver la logique de conversion Fu -> m³
                $uniteId = $uniteLivraison->id;
                $quantite = $article['quantite'];
                $categorieId = $categorieArticle->id;

                if ($uniteId == 6) { // Si l'unité est Fu
                    $uniteId = 3; // Convertir en m³ pour le stockage
                }

                $PrixTotal = $article['quantite'] * $article['prix_unitaire'];

                // 1. Créer une entrée
                Entrer::create([
                    'user_name' => $userName,
                    'article_id' => $article['article_id'],
                    'categorie_article_id' => $categorieId,
                    'lieu_stockage_id' => $article['lieu_stockage_id'],
                    'unite_id' => $uniteId,
                    'quantite' => $quantite,
                    'prix_unitaire' => $article['prix_unitaire'],
                    'prix_total' => $PrixTotal,
                    'motif' => $article['motif'] ?? null,
                    'entre' => $article['entre'],
                ]);

                // 2. Mettre à jour le stock correspondant
                $stock = Stock::firstOrCreate(
                    [
                        'article_id' => $article['article_id'],
                        'lieu_stockage_id' => $article['lieu_stockage_id'],
                    ],
                    [
                        'quantite' => 0,
                        'categorie_article_id' => $categorieId,
                    ]
                );


                $stock->increment('quantite', $quantite);

                Log::info('Stock mis à jour', [
                    'article_id' => $article['article_id'],
                    'lieu_stockage_id' => $article['lieu_stockage_id'],
                    'quantite' => $stock->quantite,
                    'unite_id' => $uniteId
                ]);
            }
        });

        return response()->json([
            'message' => 'Entrées enregistrées avec succès.',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $entree = Entrer::with(['article', 'article.uniteLivraison', 'lieuStockage', 'unite'])->findOrFail($id);
        return response()->json($entree);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id, $categorieId)
    {
        $entree = Entrer::with(['article', 'article.uniteLivraison', 'article.categorie', 'lieuStockage', 'unite'])
            ->where('categorie_article_id', $categorieId)->findOrFail($id);
        return response()->json($entree);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'article_id' => 'required|exists:article_depots,id',
            'lieu_stockage_id' => 'required|exists:lieu_stockages,id',
            'quantite' => 'required|numeric',
            'prix_unitaire' => 'nullable|numeric',
            'prix_total' => 'nullable|numeric',
            'motif' => 'nullable|string|max:255',
            'entre' => 'nullable|date',
        ]);

        $entree = Entrer::findOrFail($id);

        DB::transaction(function () use ($request, $entree) {
            // Récupérer l'article pour obtenir son unité de livraison
            $articleDepot = ArticleDepot::with('uniteLivraison')->find($request->article_id);

            if (!$articleDepot || !$articleDepot->uniteLivraison) {
                throw new \Exception("Article non trouvé ou sans unité de livraison définie");
            }

            $uniteLivraison = $articleDepot->uniteLivraison;
            $uniteId = $uniteLivraison->id;

            if ($uniteId == 6) {
                $uniteId = 3;
            }

            $ancienneQuantite = $entree->quantite;
            $nouvelleQuantite = $request->quantite;

            $PrixTotal = $request->quantite * $request->prix_unitaire;

            // Mettre à jour l'entrée
            $entree->update([
                'article_id' => $request->article_id,
                'lieu_stockage_id' => $request->lieu_stockage_id,
                'unite_id' => $uniteId,
                'quantite' => $nouvelleQuantite,
                'prix_unitaire' => $request->prix_unitaire,
                'prix_total' => $PrixTotal,
                'motif' => $request->motif,
                'entre' => $request->entre,
            ]);

            // Ajuster le stock
            if ($entree->article_id != $request->article_id || $entree->lieu_stockage_id != $request->lieu_stockage_id) {
                // Décrementer l'ancien stock
                $ancienStock = Stock::where('article_id', $entree->article_id)
                    ->where('lieu_stockage_id', $entree->lieu_stockage_id)
                    ->first();

                if ($ancienStock) {
                    $ancienStock->decrement('quantite', $ancienneQuantite);
                }

                // Incrémenter le nouveau stock
                $nouveauStock = Stock::firstOrCreate(
                    [
                        'article_id' => $request->article_id,
                        'lieu_stockage_id' => $request->lieu_stockage_id,
                    ],
                    ['quantite' => 0, 'categorie_article_id' => $request->categorie_article_id]
                );
                $nouveauStock->increment('quantite', $nouvelleQuantite);
            } else {
                // Même article et lieu, ajuster la différence
                $stock = Stock::where('article_id', $entree->article_id)
                    ->where('lieu_stockage_id', $entree->lieu_stockage_id)
                    ->first();

                if ($stock) {
                    $difference = $nouvelleQuantite - $ancienneQuantite;
                    $stock->increment('quantite', $difference);
                }
            }
        });

        return response()->json([
            'message' => 'Entrée modifiée avec succès.',
            'entree' => $entree->load(['article', 'lieuStockage', 'unite'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $entree = Entrer::findOrFail($id);

        DB::transaction(function () use ($entree) {
            // Décrementer le stock avant suppression
            $stock = Stock::where('article_id', $entree->article_id)
                ->where('lieu_stockage_id', $entree->lieu_stockage_id)
                ->first();

            if ($stock) {
                $stock->decrement('quantite', $entree->quantite);
            }

            $entree->delete();
        });

        return response()->json([
            'message' => 'Entrée supprimée avec succès.'
        ]);
    }

    /**
     * Export des données
     */
    public function export(Request $request)
    {
        $query = Entrer::with(['article', 'lieuStockage', 'unite']);

        // Appliquer les mêmes filtres que index()
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('motif', 'like', '%' . $search . '%')
                    ->orWhere('user_name', 'like', '%' . $search . '%')
                    ->orWhereHas('article', function ($q2) use ($search) {
                        $q2->where('nom_article', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('lieuStockage', function ($q3) use ($search) {
                        $q3->where('nom', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('entre', '>=', $request->date_start);
        }

        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('entre', '<=', $request->date_end);
        }

        $entrees = $query->orderBy('entre', 'desc')->get();

        return response()->json($entrees);
    }
}
