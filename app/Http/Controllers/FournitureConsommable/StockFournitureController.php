<?php

namespace App\Http\Controllers\FournitureConsommable;

use App\Http\Controllers\Controller;
use App\Models\FournitureConsommable\StockFourniture;
use Illuminate\Http\Request;

class StockFournitureController extends Controller
{
    /**
     * Liste standard (par couple fourniture + lieu)
     */
    public function index(Request $request)
    {
        $query = StockFourniture::with(['fourniture', 'fourniture.unite', 'lieuStockage']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('fourniture', function ($q) use ($search) {
                $q->where('nom', 'like', "%$search%");
            })->orWhereHas('lieuStockage', function ($q) use ($search) {
                $q->where('nom', 'like', "%$search%");
            });
        }

        if ($request->filled('lieu_id')) {
            $query->where('lieu_stockage_id', $request->lieu_id);
        }

        $stocks = $query->get();
        return response()->json($stocks);
    }

    /**
     * Liste agrégée par fourniture (pour l'affichage "Stock Actuel")
     */
    public function indexAggregated(Request $request)
    {
        $query = StockFourniture::with(['fourniture.unite', 'lieuStockage'])
            ->selectRaw('fourniture_id, SUM(quantite) as quantite_totale, MAX(updated_at) as last_update')
            ->groupBy('fourniture_id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('fourniture', function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%");
            });
        }

        $aggregated = $query->get();

        $result = $aggregated->map(function ($item) {
            $fourniture = $item->fourniture;
            // Récupérer tous les stocks individuels de cette fourniture
            $stocks = StockFourniture::with('lieuStockage')
                ->where('fourniture_id', $item->fourniture_id)
                ->get();

            $lieux = [];
            foreach ($stocks as $stock) {
                $lieux[$stock->lieu_stockage_id] = [
                    'nom'        => $stock->lieuStockage->nom,
                    'quantite'   => $stock->quantite,
                    'updated_at' => $stock->updated_at,
                ];
            }

            return [
                'id'                => $fourniture->id,
                'article_nom'       => $fourniture->nom,
                'quantite_totale'   => (float) $item->quantite_totale,
                'last_update'       => $item->last_update,
                'article_depot'     => [
                    'unite_livraison' => [
                        'nom_unite' => $fourniture->unite->nom_unite ?? '',
                    ],
                ],
                'lieux'             => $lieux,
            ];
        });

        return response()->json($result);
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
}
