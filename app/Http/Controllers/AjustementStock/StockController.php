<?php

namespace App\Http\Controllers\AjustementStock;

use App\Exports\StockExport;
use App\Http\Controllers\Controller;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Stock;
use App\Models\Parametre\CategorieArticle;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;

class StockController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Stock::with(['lieuStockage', 'articleDepot.uniteLivraison'])
            ->where(function ($q) {
                $q->whereNull('isAtelierMeca')->orWhere('isAtelierMeca', false);
            });

        // Ajout de la recherche par nom d'article
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->whereHas('articleDepot', function ($q) use ($searchTerm) {
                $q->where('nom_article', 'like', '%' . $searchTerm . '%');
            });
        }

        // Récupérer toutes les données sans pagination pour le regroupement
        $stocks = $query->get();

        // Regrouper par article
        $groupedStocks = $stocks->groupBy('article_id')->map(function ($articleStocks, $articleId) {
            $firstStock = $articleStocks->first();

            // Calculer la quantité totale pour cet article
            $quantiteTotale = $articleStocks->sum('quantite');

            // Préparer les détails par lieu avec updated_at
            $lieux = $articleStocks->mapWithKeys(function ($stock) {
                $lieuKey = $stock->lieu_stockage_id ? 'lieu_stockage_' . $stock->lieu_stockage_id : 'lieu_stockage_null';

                return [
                    $lieuKey => [
                        'nom' => $stock->lieuStockage->nom ?? 'Sans lieu',
                        'quantite' => $stock->quantite,
                        'updated_at' => $stock->updated_at, // Ajout de la date de mise à jour
                        'lieu_stockage_id' => $stock->lieu_stockage_id // ID du lieu pour référence
                    ]
                ];
            })->toArray();

            return [
                'id' => $firstStock->id,
                'article_id' => $articleId,
                'article_nom' => $firstStock->articleDepot->nom_article,
                'unite' => $firstStock->articleDepot->unite->nom_unite ?? 'Non défini',
                'quantite_totale' => $quantiteTotale,
                'lieux' => $lieux,
                'updated_at' => $articleStocks->max('updated_at'),
                'article_depot' => $firstStock->articleDepot,
            ];
        })->values();

        // Gestion de la pagination sur les données groupées
        if ($request->has('per_page') && $request->has('page')) {
            $perPage = $request->per_page;
            $page = $request->page;

            // Pagination manuelle sur la collection
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $currentItems = $groupedStocks->slice(($currentPage - 1) * $perPage, $perPage)->values();

            $paginatedStocks = new LengthAwarePaginator(
                $currentItems,
                $groupedStocks->count(),
                $perPage,
                $currentPage,
                ['path' => LengthAwarePaginator::resolveCurrentPath()]
            );

            return response()->json([
                'data' => $paginatedStocks->items(),
                'total' => $paginatedStocks->total(),
                'current_page' => $paginatedStocks->currentPage(),
                'per_page' => $paginatedStocks->perPage(),
                'last_page' => $paginatedStocks->lastPage(),
            ]);
        }

        return response()->json($groupedStocks);
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new StockExport($request), 'Stock.xlsx');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function getStockGasoil()
    {
        $stocks = Stock::with(['lieuStockage', 'articleDepot'])
            ->where(function ($q) {
                $q->whereNull('isAtelierMeca')->orWhere('isAtelierMeca', false);
            })
            ->whereHas('articleDepot', function ($query) {
                $query->whereRaw('LOWER(nom_article) = ?', ['gasoil']);
            })
            ->get()
            ->groupBy('lieu_stockage_id')
            ->map(function ($items, $lieuId) {
                $firstItem = $items->first();
                return [
                    'id' => $lieuId,
                    'lieu_stockage' => $firstItem->lieuStockage->nom ?? 'Sans lieu',
                    'quantite' => $items->sum('quantite')
                ];
            });

        return response()->json($stocks->values());
    }

    public function getStockHuile()
    {
        // Trouver la catégorie huile
        $categorieHuile = CategorieArticle::where('nom_categorie', 'like', '%huile%')->first();

        if (!$categorieHuile) {
            return response()->json([]);
        }

        // Récupérer les stocks d'huiles groupés par lieu de stockage
        $stocks = Stock::with(['lieuStockage', 'articleDepot.categorie', 'articleDepot.uniteLivraison'])
            ->where(function ($q) {
                $q->whereNull('isAtelierMeca')->orWhere('isAtelierMeca', false);
            })
            ->whereHas('articleDepot', function ($query) use ($categorieHuile) {
                $query->where('categorie_id', $categorieHuile->id);
            })
            ->get()
            ->groupBy('lieu_stockage_id')
            ->map(function ($lieuStocks, $lieuId) {
                $firstStock = $lieuStocks->first();

                // Liste des huiles dans ce lieu de stockage
                $huiles = $lieuStocks->map(function ($stock) {
                    return [
                        'article_id' => $stock->article_id,
                        'article_nom' => $stock->articleDepot->nom_article,
                        'designation' => $stock->articleDepot->designation,
                        'categorie' => $stock->articleDepot->categorie->nom_categorie,
                        'quantite' => $stock->quantite,
                        'unite' => $stock->articleDepot->uniteLivraison->nom_unite ?? 'Non défini',
                        'quantite_max_livraison' => $stock->articleDepot->quantite_max_livraison,
                        'unite_livraison' => $stock->articleDepot->nom_unite_livraison,
                        'updated_at' => $stock->updated_at,
                        'stock_id' => $stock->id
                    ];
                })->values();

                // Quantité totale d'huiles dans ce lieu
                $quantiteTotale = $lieuStocks->sum('quantite');

                return [
                    'lieu_stockage_id' => $lieuId,
                    'nom_lieu' => $firstStock->lieuStockage->nom,
                    'quantite_totale' => $quantiteTotale,
                    'nombre_huiles' => $lieuStocks->count(),
                    'huiles' => $huiles,
                    'updated_at' => $lieuStocks->max('updated_at')
                ];
            })->values();

        return response()->json($stocks);
    }

    public function getStockGasoilAtelierMeca()
    {
        $articleGasoil = ArticleDepot::whereRaw('LOWER(nom_article) = ?', ['gasoil'])->first();

        if (!$articleGasoil) {
            return response()->json([
                'message' => 'Article gasoil introuvable'
            ], 404);
        }

        $stockAtelier = Stock::where('article_id', $articleGasoil->id)
            ->where('isAtelierMeca', true)
            ->whereNull('lieu_stockage_id')
            ->first();

        return response()->json([
            'stock_id' => $stockAtelier?->id,
            'article_id' => $articleGasoil->id,
            'article_nom' => $articleGasoil->nom_article,
            'quantite' => (float) ($stockAtelier->quantite ?? 0),
            'isAtelierMeca' => true,
            'lieu_stockage_id' => null,
            'updated_at' => $stockAtelier?->updated_at
        ]);
    }
}
