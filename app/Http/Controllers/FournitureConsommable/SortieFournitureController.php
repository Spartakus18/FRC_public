<?php

namespace App\Http\Controllers\FournitureConsommable;

use App\Http\Controllers\Controller;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\FournitureConsommable\FournitureConsommable;
use App\Models\FournitureConsommable\SortieFourniture;
use App\Models\FournitureConsommable\StockFourniture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SortieFournitureController extends Controller
{
    public function index(Request $request)
    {
        $query = SortieFourniture::with(['fourniture', 'fourniture.unite', 'lieuStockage', 'unite']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('motif', 'like', "%$search%")
                    ->orWhere('user_name', 'like', "%$search%")
                    ->orWhere('demande_par', 'like', "%$search%")
                    ->orWhere('sortie_par', 'like', "%$search%")
                    ->orWhereHas('fourniture', function ($q2) use ($search) {
                        $q2->where('nom', 'like', "%$search%");
                    })
                    ->orWhereHas('lieuStockage', function ($q3) use ($search) {
                        $q3->where('nom', 'like', "%$search%");
                    });
            });
        }

        if ($request->filled('date_start')) {
            $query->whereDate('sortie', '>=', $request->date_start);
        }
        if ($request->filled('date_end')) {
            $query->whereDate('sortie', '<=', $request->date_end);
        }

        $query->orderBy('sortie', 'desc');

        $perPage = $request->per_page ?? 10;
        $sorties = $query->paginate($perPage);

        return response()->json($sorties);
    }

    public function create()
    {
        $stocks = StockFourniture::with(['fourniture', 'fourniture.unite', 'lieuStockage'])->get();
        $lieux = Lieu_stockage::all();

        return response()->json([
            'stocks' => $stocks,
            'lieux' => $lieux,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'articles' => 'required|array|min:1',
            'articles.*.fourniture_id' => 'required|exists:fournitures,id',
            'articles.*.lieu_stockage_id' => 'required|exists:lieu_stockages,id',
            'articles.*.quantite' => 'required|numeric|min:0.1',
            'articles.*.demande_par' => 'nullable|string',
            'articles.*.sortie_par' => 'nullable|string',
            'articles.*.motif' => 'nullable|string|max:255',
        ]);

        $userName = Auth::user()->nom ?? 'system';

        DB::transaction(function () use ($request, $userName) {
            foreach ($request->articles as $article) {
                $fourniture = FournitureConsommable::with('unite')->find($article['fourniture_id']);

                $stock = StockFourniture::where('fourniture_id', $article['fourniture_id'])
                    ->where('lieu_stockage_id', $article['lieu_stockage_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->quantite < $article['quantite']) {
                    throw new \Exception("Stock insuffisant pour la fourniture ID {$article['fourniture_id']}");
                }

                SortieFourniture::create([
                    'user_name' => $userName,
                    'fourniture_id' => $article['fourniture_id'],
                    'lieu_stockage_id' => $article['lieu_stockage_id'],
                    'unite_id' => $fourniture->unite_id,
                    'quantite' => $article['quantite'],
                    'demande_par' => $article['demande_par'] ?? null,
                    'sortie_par' => $article['sortie_par'] ?? null,
                    'motif' => $article['motif'] ?? null,
                    'sortie' => now()->toDateString(),
                ]);

                $stock->decrement('quantite', $article['quantite']);
            }
        });

        return response()->json(['message' => 'Sorties enregistrées avec succès'], 201);
    }

    public function show($id)
    {
        $sortie = SortieFourniture::with(['fourniture', 'lieuStockage', 'unite'])->findOrFail($id);
        return response()->json($sortie);
    }

    public function update(Request $request, $id)
    {
        $sortie = SortieFourniture::findOrFail($id);

        $request->validate([
            'fourniture_id' => 'required|exists:fournitures,id',
            'lieu_stockage_id' => 'required|exists:lieu_stockages,id',
            'quantite' => 'required|numeric|min:0.1',
            'demande_par' => 'nullable|string',
            'sortie_par' => 'nullable|string',
            'motif' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $sortie) {
            // Restaurer l'ancien stock
            $ancienStock = StockFourniture::where('fourniture_id', $sortie->fourniture_id)
                ->where('lieu_stockage_id', $sortie->lieu_stockage_id)
                ->first();
            if ($ancienStock) {
                $ancienStock->increment('quantite', $sortie->quantite);
            }

            $fourniture = FournitureConsommable::find($request->fourniture_id);

            // Vérifier le nouveau stock
            $nouveauStock = StockFourniture::where('fourniture_id', $request->fourniture_id)
                ->where('lieu_stockage_id', $request->lieu_stockage_id)
                ->first();

            if (!$nouveauStock || $nouveauStock->quantite < $request->quantite) {
                // Remettre l'ancien stock ? On est en transaction, on peut rollback en lançant une exception
                throw new \Exception("Stock insuffisant pour la modification");
            }

            $sortie->update([
                'fourniture_id' => $request->fourniture_id,
                'lieu_stockage_id' => $request->lieu_stockage_id,
                'unite_id' => $fourniture->unite_id,
                'quantite' => $request->quantite,
                'demande_par' => $request->demande_par,
                'sortie_par' => $request->sortie_par,
                'motif' => $request->motif,
                'sortie' => now()->toDateString(),
            ]);

            $nouveauStock->decrement('quantite', $request->quantite);
        });

        return response()->json(['message' => 'Sortie modifiée avec succès']);
    }

    public function destroy($id)
    {
        $sortie = SortieFourniture::findOrFail($id);

        DB::transaction(function () use ($sortie) {
            $stock = StockFourniture::where('fourniture_id', $sortie->fourniture_id)
                ->where('lieu_stockage_id', $sortie->lieu_stockage_id)
                ->first();
            if ($stock) {
                $stock->increment('quantite', $sortie->quantite);
            }
            $sortie->delete();
        });

        return response()->json(['message' => 'Sortie supprimée avec succès']);
    }
}
