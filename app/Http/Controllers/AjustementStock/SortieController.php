<?php

namespace App\Http\Controllers\AjustementStock;

use App\Http\Controllers\Controller;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\AjustementStock\Sortie;
use App\Models\AjustementStock\Stock;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\Parametre\Unite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SortieController extends Controller
{
    /**
     * Display a listing of the resource (toutes catégories).
     */
    public function index(Request $request)
    {
        $query = Sortie::with(['articleDepot', 'lieuStockage', 'unite']);

        // Filtre par recherche
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('motif', 'like', '%' . $search . '%')
                    ->orWhere('user_name', 'like', '%' . $search . '%')
                    ->orWhereHas('articleDepot', function ($q2) use ($search) {
                        $q2->where('nom_article', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('lieuStockage', function ($q3) use ($search) {
                        $q3->where('nom', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filtre par date de début
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('sortie', '>=', $request->date_start);
        }

        // Filtre par date de fin
        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('sortie', '<=', $request->date_end);
        }

        // Tri par défaut (du plus récent au plus ancien)
        $query->orderBy('sortie', 'desc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $sorties = $query->paginate($perPage);

        return response()->json($sorties);
    }

    /**
     * Index filtré par catégorie (Gas-oil)
     */
    public function indexGasoil(Request $request)
    {
        return $this->indexByCategorie($request, 1);
    }

    /**
     * Index filtré par catégorie (Huile)
     */
    public function indexHuile(Request $request)
    {
        return $this->indexByCategorie($request, 2);
    }

    /**
     * Index filtré par catégorie (Produit)
     */
    public function indexProduit(Request $request)
    {
        return $this->indexByCategorie($request, 3);
    }

    /**
     * Méthode privée pour filtrer l'index par catégorie
     */
    private function indexByCategorie(Request $request, $categorieId)
    {
        $query = Sortie::with(['articleDepot', 'articleDepot.uniteLivraison', 'lieuStockage', 'unite'])
                       ->where('categorie_article_id', $categorieId);

        // Filtre par recherche
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('motif', 'like', '%' . $search . '%')
                    ->orWhere('user_name', 'like', '%' . $search . '%')
                    ->orWhereHas('articleDepot', function ($q2) use ($search) {
                        $q2->where('nom_article', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('lieuStockage', function ($q3) use ($search) {
                        $q3->where('nom', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filtre par date de début
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('sortie', '>=', $request->date_start);
        }

        // Filtre par date de fin
        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('sortie', '<=', $request->date_end);
        }

        $query->orderBy('sortie', 'desc');

        $perPage = $request->per_page ?? 10;
        $sorties = $query->paginate($perPage);

        return response()->json($sorties);
    }

    /**
     * Show the form for creating a new resource (toutes catégories)
     */
    public function create()
    {
        $stock = Stock::with(['articleDepot', 'articleDepot.uniteLivraison', 'lieuStockage'])->get();
        $lieuStockage = Lieu_stockage::all();

        return response()->json([
            'stock' => $stock,
            'lieuStockage' => $lieuStockage,
        ]);
    }

    /**
     * Show the form for creating a new resource (Gas-oil)
     */
    public function createGasoil()
    {
        return $this->createByCategorie(1);
    }

    /**
     * Show the form for creating a new resource (Huile)
     */
    public function createHuile()
    {
        return $this->createByCategorie(2);
    }

    /**
     * Show the form for creating a new resource (Produit)
     */
    public function createProduit()
    {
        return $this->createByCategorie(3);
    }

    /**
     * Méthode privée pour filtrer les stocks par catégorie
     */
    private function createByCategorie($categorieId)
    {
        $stock = Stock::with(['articleDepot', 'articleDepot.uniteLivraison', 'lieuStockage'])
                      ->whereHas('articleDepot', function ($q) use ($categorieId) {
                          $q->where('categorie_id', $categorieId);
                      })
                      ->get();
        $lieuStockage = Lieu_stockage::all();

        return response()->json([
            'stock' => $stock,
            'lieuStockage' => $lieuStockage,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'articles' => 'required|array|min:1',
            'articles.*.article_id' => 'required|exists:article_depots,id',
            'articles.*.lieu_stockage_id' => 'required|exists:lieu_stockages,id',
            'articles.*.demande_par' => 'nullable|string',
            'articles.*.sortie_par' => 'nullable|string',
            'articles.*.quantite' => 'required|numeric|min:0.1',
            'articles.*.motif' => 'nullable|string|max:255',
        ]);

        $userName = Auth::user()->nom ?? 'system';

        DB::transaction(function () use ($request, $userName) {
            foreach ($request->articles as $article) {
                // 1. Vérifier que le stock existe et est suffisant
                $stock = Stock::where('article_id', $article['article_id'])
                    ->where('lieu_stockage_id', $article['lieu_stockage_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$stock) {
                    throw new \Exception("Stock non trouvé pour l'article ID {$article['article_id']} dans le lieu de stockage {$article['lieu_stockage_id']}");
                }

                if ($stock->quantite < $article['quantite']) {
                    throw new \Exception("Stock insuffisant pour l'article {$stock->articleDepot->nom_article}. Disponible: {$stock->quantite}, Demandé: {$article['quantite']}");
                }

                // 2. Récupérer l'article et sa catégorie
                $articleDepot = ArticleDepot::with('categorie')->find($article['article_id']);
                if (!$articleDepot->unite_livraison_id) {
                    throw new \Exception("L'article {$articleDepot->nom_article} n'a pas d'unité de livraison définie.");
                }
                $categorieId = $articleDepot->categorie_id;

                // 3. Créer une sortie
                Sortie::create([
                    'user_name' => $userName,
                    'demande_par' => $article['demande_par'] ?? null,
                    'sortie_par' => $article['sortie_par'] ?? null,
                    'article_id' => $article['article_id'],
                    'categorie_article_id' => $categorieId,
                    'lieu_stockage_id' => $article['lieu_stockage_id'],
                    'quantite' => $article['quantite'],
                    'unite_id' => $articleDepot->unite_livraison_id,
                    'motif' => $article['motif'] ?? null,
                    'sortie' => now()->toDateString(),
                ]);

                // 4. Mettre à jour le stock
                $stock->decrement('quantite', $article['quantite']);

                Log::info("Stock mis à jour après sortie", [
                    'article_id' => $article['article_id'],
                    'lieu_stockage_id' => $article['lieu_stockage_id'],
                    'quantite_sortie' => $article['quantite'],
                    'stock_restant' => $stock->quantite,
                ]);
            }
        });

        return response()->json([
            'message' => 'Sortie de stock enregistrée avec succès.',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $sortie = Sortie::with(['articleDepot', 'articleDepot.uniteLivraison', 'lieuStockage'])->findOrFail($id);
        return response()->json($sortie);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $sortie = Sortie::with(['articleDepot', 'articleDepot.uniteLivraison', 'lieuStockage'])->findOrFail($id);
        $lieuStockage = Lieu_stockage::all();

        return response()->json([
            'sortie' => $sortie,
            'lieuStockage' => $lieuStockage,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'article_id' => 'required|exists:article_depots,id',
            'lieu_stockage_id' => 'required|exists:lieu_stockages,id',
            'quantite' => 'required|numeric|min:0.1',
            'motif' => 'nullable|string|max:255',
            'demande_par' => 'nullable|string',
            'sortie_par' => 'nullable|string',
        ]);

        $sortie = Sortie::findOrFail($id);

        DB::transaction(function () use ($request, $sortie) {
            $ancienneQuantite = $sortie->quantite;
            $nouvelleQuantite = $request->quantite;

            // Remettre l'ancien stock
            $ancienStock = Stock::where('article_id', $sortie->article_id)
                ->where('lieu_stockage_id', $sortie->lieu_stockage_id)
                ->first();

            if ($ancienStock) {
                $ancienStock->increment('quantite', $ancienneQuantite);
            }

            // Récupérer l'article pour son unité et sa catégorie
            $articleDepot = ArticleDepot::with('categorie')->find($request->article_id);
            if (!$articleDepot->unite_livraison_id) {
                throw new \Exception("L'article {$articleDepot->nom_article} n'a pas d'unité de livraison définie.");
            }
            $categorieId = $articleDepot->categorie_id;

            // Mettre à jour la sortie
            $sortie->update([
                'article_id' => $request->article_id,
                'lieu_stockage_id' => $request->lieu_stockage_id,
                'quantite' => $nouvelleQuantite,
                'motif' => $request->motif,
                'demande_par' => $request->demande_par ?? null,
                'sortie_par' => $request->sortie_par ?? null,
                'unite_id' => $articleDepot->unite_livraison_id,
                'categorie_article_id' => $categorieId,
                'sortie' => now()->toDateString(),
            ]);

            // Déduire le nouveau stock
            $nouveauStock = Stock::where('article_id', $request->article_id)
                ->where('lieu_stockage_id', $request->lieu_stockage_id)
                ->first();

            if (!$nouveauStock) {
                throw new \Exception("Stock non trouvé pour l'article ID {$request->article_id} dans le lieu de stockage {$request->lieu_stockage_id}");
            }

            if ($nouveauStock->quantite < $nouvelleQuantite) {
                throw new \Exception("Stock insuffisant pour la modification. Disponible: {$nouveauStock->quantite}, Demandé: {$nouvelleQuantite}");
            }

            $nouveauStock->decrement('quantite', $nouvelleQuantite);
        });

        return response()->json([
            'message' => 'Sortie modifiée avec succès.',
            'sortie' => $sortie->load(['articleDepot', 'articleDepot.uniteLivraison', 'lieuStockage'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $sortie = Sortie::findOrFail($id);

        DB::transaction(function () use ($sortie) {
            // Remettre le stock avant suppression
            $stock = Stock::where('article_id', $sortie->article_id)
                ->where('lieu_stockage_id', $sortie->lieu_stockage_id)
                ->first();

            if ($stock) {
                $stock->increment('quantite', $sortie->quantite);
            }

            $sortie->delete();
        });

        return response()->json([
            'message' => 'Sortie supprimée avec succès.'
        ]);
    }

    /**
     * Export des données
     */
    public function export(Request $request)
    {
        $query = Sortie::with(['articleDepot', 'articleDepot.uniteLivraison', 'lieuStockage']);

        // Appliquer les mêmes filtres que index()
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('motif', 'like', '%' . $search . '%')
                    ->orWhere('user_name', 'like', '%' . $search . '%')
                    ->orWhereHas('articleDepot', function ($q2) use ($search) {
                        $q2->where('nom_article', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('lieuStockage', function ($q3) use ($search) {
                        $q3->where('nom', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('sortie', '>=', $request->date_start);
        }

        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('sortie', '<=', $request->date_end);
        }

        $sorties = $query->orderBy('sortie', 'desc')->get();

        return response()->json($sorties);
    }
}