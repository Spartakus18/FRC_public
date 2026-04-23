<?php

namespace App\Http\Controllers\FournitureConsommable;

use App\Http\Controllers\Controller;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\FournitureConsommable\EntreeFourniture;
use App\Models\FournitureConsommable\FournitureConsommable;
use App\Models\FournitureConsommable\StockFourniture;
use App\Models\Parametre\Unite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EntreeFournitureController extends Controller
{
    public function index(Request $request)
    {
        $query = EntreeFourniture::with(['fourniture', 'fourniture.unite', 'lieuStockage', 'unite']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('motif', 'like', "%$search%")
                    ->orWhere('user_name', 'like', "%$search%")
                    ->orWhereHas('fourniture', function ($q2) use ($search) {
                        $q2->where('nom', 'like', "%$search%");
                    })
                    ->orWhereHas('lieuStockage', function ($q3) use ($search) {
                        $q3->where('nom', 'like', "%$search%");
                    });
            });
        }

        if ($request->filled('date_start')) {
            $query->whereDate('entre', '>=', $request->date_start);
        }
        if ($request->filled('date_end')) {
            $query->whereDate('entre', '<=', $request->date_end);
        }

        $query->orderBy('entre', 'desc');

        $perPage = $request->per_page ?? 10;
        $entrees = $query->paginate($perPage);

        return response()->json($entrees);
    }

    public function create()
    {
        $fournitures = FournitureConsommable::with('unite')->get();
        $lieux = Lieu_stockage::all();
        $unites = Unite::all();

        return response()->json([
            'fournitures' => $fournitures,
            'lieux' => $lieux,
            'unites' => $unites,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'articles' => 'required|array|min:1',
            'articles.*.fourniture_id' => 'required|exists:fourniture_consommables,id',
            'articles.*.lieu_stockage_id' => 'required|exists:lieu_stockages,id',
            'articles.*.quantite' => 'required|numeric|min:0',
            'articles.*.prix_unitaire' => 'nullable|numeric|min:0',
            'articles.*.motif' => 'nullable|string|max:255',
            'articles.*.entre' => 'required|date',
        ]);

        $userName = Auth::user()->nom ?? 'system';

        DB::transaction(function () use ($request, $userName) {
            foreach ($request->articles as $article) {
                $fourniture = FournitureConsommable::with('unite')->find($article['fourniture_id']);

                $prixTotal = $article['prix_unitaire'] ? $article['quantite'] * $article['prix_unitaire'] : null;

                EntreeFourniture::create([
                    'user_name' => $userName,
                    'fourniture_id' => $article['fourniture_id'],
                    'lieu_stockage_id' => $article['lieu_stockage_id'],
                    'unite_id' => $fourniture->unite_id,
                    'quantite' => $article['quantite'],
                    'prix_unitaire' => $article['prix_unitaire'] ?? null,
                    'prix_total' => $prixTotal,
                    'motif' => $article['motif'] ?? null,
                    'entre' => $article['entre'],
                ]);

                $stock = StockFourniture::firstOrCreate(
                    [
                        'fourniture_id' => $article['fourniture_id'],
                        'lieu_stockage_id' => $article['lieu_stockage_id'],
                    ],
                    ['quantite' => 0]
                );
                $stock->increment('quantite', $article['quantite']);
            }
        });

        return response()->json(['message' => 'Entrées enregistrées avec succès'], 201);
    }

    public function show($id)
    {
        $entree = EntreeFourniture::with(['fourniture', 'lieuStockage', 'unite'])->findOrFail($id);
        return response()->json($entree);
    }

    public function update(Request $request, $id)
    {
        $entree = EntreeFourniture::findOrFail($id);

        $request->validate([
            'fourniture_id' => 'required|exists:fourniture_consommables,id',
            'lieu_stockage_id' => 'required|exists:lieu_stockages,id',
            'quantite' => 'required|numeric|min:0',
            'prix_unitaire' => 'nullable|numeric|min:0',
            'motif' => 'nullable|string|max:255',
            'entre' => 'required|date',
        ]);

        DB::transaction(function () use ($request, $entree) {
            $fourniture = FournitureConsommable::find($request->fourniture_id);

            // Restaurer l'ancien stock
            $ancienStock = StockFourniture::where('fourniture_id', $entree->fourniture_id)
                ->where('lieu_stockage_id', $entree->lieu_stockage_id)
                ->first();
            if ($ancienStock) {
                $ancienStock->decrement('quantite', $entree->quantite);
            }

            $prixTotal = $request->prix_unitaire ? $request->quantite * $request->prix_unitaire : null;
            $entree->update([
                'fourniture_id' => $request->fourniture_id,
                'lieu_stockage_id' => $request->lieu_stockage_id,
                'unite_id' => $fourniture->unite_id,
                'quantite' => $request->quantite,
                'prix_unitaire' => $request->prix_unitaire,
                'prix_total' => $prixTotal,
                'motif' => $request->motif,
                'entre' => $request->entre,
            ]);

            $nouveauStock = StockFourniture::firstOrCreate(
                [
                    'fourniture_id' => $request->fourniture_id,
                    'lieu_stockage_id' => $request->lieu_stockage_id,
                ],
                ['quantite' => 0]
            );
            $nouveauStock->increment('quantite', $request->quantite);
        });

        return response()->json(['message' => 'Entrée modifiée avec succès']);
    }

    public function destroy($id)
    {
        $entree = EntreeFourniture::findOrFail($id);

        DB::transaction(function () use ($entree) {
            $stock = StockFourniture::where('fourniture_id', $entree->fourniture_id)
                ->where('lieu_stockage_id', $entree->lieu_stockage_id)
                ->first();
            if ($stock) {
                $stock->decrement('quantite', $entree->quantite);
            }
            $entree->delete();
        });

        return response()->json(['message' => 'Entrée supprimée avec succès']);
    }
}
