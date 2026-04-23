<?php

namespace App\Http\Controllers\Produit;

use App\Exports\MelangeProduitExport;
use App\Http\Controllers\Controller;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Entrer;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\AjustementStock\Sortie;
use App\Models\AjustementStock\Stock;
use App\Models\Parametre\Unite;
use App\Models\Produit\MelangeProduit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class MelangeProduitController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = MelangeProduit::with(['produitA', 'produitB', 'lieuStockageA', 'lieuStockageB', 'lieuStockageFinal', 'uniteLivraison']);

        // Filtre par recherche
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('observation', 'like', '%' . $search . '%')
                    ->orWhereHas('produitB', function ($q2) use ($search) {
                        $q2->where('nom_article', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('produitA', function ($q3) use ($search) {
                        $q3->where('nom_article', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filtre par date de début
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('date', '>=', $request->date_start);
        }

        // Filtre par date de fin
        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('date', '<=', $request->date_end);
        }

        // Tri par date décroissante
        $query->orderBy('date', 'desc')->orderBy('id', 'desc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $melange = $query->paginate($perPage);

        return response()->json($melange);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // Récupérer tous les produits avec leur unité de livraison
        $produits = ArticleDepot::with('uniteLivraison')->get();
        $lieuxStockage = Lieu_stockage::all();

        return response()->json([
            'produits' => $produits,
            'lieuxStockage' => $lieuxStockage,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Restriction d'ajout
        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3])) {
            return response(['message' => 'Action non autorisée'], 403);
        }

        $request->validate([
            'date' => 'required|date',
            'produit_a_id' => 'required|exists:article_depots,id',
            'produit_b_id' => 'required|exists:article_depots,id|different:produit_a_id',
            'lieu_stockage_a_id' => 'required|exists:lieu_stockages,id',
            'lieu_stockage_b_id' => 'required|exists:lieu_stockages,id',
            'lieu_stockage_final_id' => 'required|exists:lieu_stockages,id',
            'quantite_a' => 'required|numeric|min:0.1',
            'quantite_b_consommee' => 'required|numeric|min:0.1',
            'quantite_b_produite' => 'required|numeric|min:0.1',
            'observation' => 'nullable|string',
        ]);

        // Validation: la quantité produite doit être égale à la somme des quantités A et B consommées
        $sommeAttendue = $request->quantite_a + $request->quantite_b_consommee;
        if (abs($request->quantite_b_produite - $sommeAttendue) > 0.01) {
            return response()->json([
                'message' => 'La quantité produite doit être égale à la somme des quantités A et B consommées'
            ], 422);
        }

        // Récupérer le produit B pour obtenir son unité de livraison
        $produitB = ArticleDepot::with('uniteLivraison')->find($request->produit_b_id);
        if (!$produitB || !$produitB->unite_livraison_id) {
            return response()->json([
                'message' => 'Produit B ou unité de livraison non trouvés'
            ], 422);
        }

        // Vérifier les stocks disponibles pour les produits
        $stockA = Stock::where([
            'article_id' => $request->produit_a_id,
            'lieu_stockage_id' => $request->lieu_stockage_a_id
        ])->first();

        if (!$stockA || $stockA->quantite < $request->quantite_a) {
            return response()->json([
                'message' => 'Stock insuffisant pour le produit A'
            ], 422);
        }

        $stockB = Stock::where([
            'article_id' => $request->produit_b_id,
            'lieu_stockage_id' => $request->lieu_stockage_b_id
        ])->first();

        if (!$stockB || $stockB->quantite < $request->quantite_b_consommee) {
            return response()->json([
                'message' => 'Stock insuffisant pour le produit B'
            ], 422);
        }

        // Créer le mélange
        $melange = MelangeProduit::create([
            'date' => $request->date,
            'produit_a_id' => $request->produit_a_id,
            'produit_b_id' => $request->produit_b_id,
            'lieu_stockage_a_id' => $request->lieu_stockage_a_id,
            'lieu_stockage_b_id' => $request->lieu_stockage_b_id,
            'lieu_stockage_final_id' => $request->lieu_stockage_final_id,
            'unite_livraison_id' => $produitB->unite_livraison_id,
            'quantite_a' => $request->quantite_a,
            'quantite_b_consommee' => $request->quantite_b_consommee,
            'quantite_b_produite' => $request->quantite_b_produite,
            'observation' => $request->observation,
        ]);

        // Appliquer les effets sur les stocks
        $this->appliquerMelange($melange, $user);

        return response()->json([
            'message' => 'Mélange créé avec succès',
            'melange' => $melange
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $melange = MelangeProduit::with([
            'produitA',
            'produitB',
            'lieuStockageA',
            'lieuStockageB',
            'lieuStockageFinal',
            'uniteLivraison'
        ])->find($id);

        if (!$melange) {
            return response()->json(['message' => 'Mélange non trouvé'], 404);
        }

        return $melange;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $melange = MelangeProduit::with([
            'produitA',
            'produitB',
            'lieuStockageA',
            'lieuStockageB',
            'lieuStockageFinal',
            'uniteLivraison'
        ])->find($id);

        if (!$melange) {
            return response()->json(['message' => 'Mélange non trouvé'], 404);
        }

        return $melange;
    }

    public function export(Request $request)
    {
        return Excel::download(new MelangeProduitExport($request), 'melanges-produits-' . date('Y-m-d') . '.xlsx');
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
        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3])) {
            return response(['message' => 'Action non autorisée'], 403);
        }

        $request->validate([
            'date' => 'required|date',
            'produit_a_id' => 'required|exists:article_depots,id',
            'produit_b_id' => 'required|exists:article_depots,id|different:produit_a_id',
            'lieu_stockage_a_id' => 'required|exists:lieu_stockages,id',
            'lieu_stockage_b_id' => 'required|exists:lieu_stockages,id',
            'lieu_stockage_final_id' => 'required|exists:lieu_stockages,id',
            'quantite_a' => 'required|numeric|min:0.1',
            'quantite_b_consommee' => 'required|numeric|min:0.1',
            'quantite_b_produite' => 'required|numeric|min:0.1',
            'observation' => 'nullable|string',
        ]);

        // Validation: la quantité produite doit être égale à la somme des quantités A et B consommées
        $sommeAttendue = $request->quantite_a + $request->quantite_b_consommee;
        if (abs($request->quantite_b_produite - $sommeAttendue) > 0.01) {
            return response()->json([
                'message' => 'La quantité produite doit être égale à la somme des quantités A et B consommées'
            ], 422);
        }

        $melange = MelangeProduit::find($id);

        if (!$melange) {
            return response()->json(['message' => 'Mélange non trouvé'], 404);
        }

        // Récupérer le produit B pour obtenir son unité de livraison
        $produitB = ArticleDepot::with('uniteLivraison')->find($request->produit_b_id);
        if (!$produitB || !$produitB->uniteLivraison) {
            return response()->json([
                'message' => 'Produit B ou unité de livraison non trouvés'
            ], 422);
        }

        // Vérifier les stocks disponibles si les quantités changent
        if (
            $request->quantite_a != $melange->quantite_a ||
            $request->produit_a_id != $melange->produit_a_id ||
            $request->lieu_stockage_a_id != $melange->lieu_stockage_a_id
        ) {
            $stockA = Stock::where([
                'article_id' => $request->produit_a_id,
                'lieu_stockage_id' => $request->lieu_stockage_a_id
            ])->first();

            if (!$stockA || $stockA->quantite < $request->quantite_a) {
                return response()->json([
                    'message' => 'Stock insuffisant pour le produit A'
                ], 422);
            }
        }

        if (
            $request->quantite_b_consommee != $melange->quantite_b_consommee ||
            $request->produit_b_id != $melange->produit_b_id ||
            $request->lieu_stockage_b_id != $melange->lieu_stockage_b_id
        ) {
            $stockB = Stock::where([
                'article_id' => $request->produit_b_id,
                'lieu_stockage_id' => $request->lieu_stockage_b_id
            ])->first();

            if (!$stockB || $stockB->quantite < $request->quantite_b_consommee) {
                return response()->json([
                    'message' => 'Stock insuffisant pour le produit B'
                ], 422);
            }
        }

        // Annuler l'ancien mélange
        $this->annulerMelange($melange);

        // Mettre à jour le mélange
        $melange->update([
            'date' => $request->date,
            'produit_a_id' => $request->produit_a_id,
            'produit_b_id' => $request->produit_b_id,
            'lieu_stockage_a_id' => $request->lieu_stockage_a_id,
            'lieu_stockage_b_id' => $request->lieu_stockage_b_id,
            'lieu_stockage_final_id' => $request->lieu_stockage_final_id,
            'unite_livraison_id' => $produitB->unite_livraison_id,
            'quantite_a' => $request->quantite_a,
            'quantite_b_consommee' => $request->quantite_b_consommee,
            'quantite_b_produite' => $request->quantite_b_produite,
            'observation' => $request->observation,
        ]);

        // Appliquer le nouveau mélange
        $this->appliquerMelange($melange, $user);

        return response()->json([
            'message' => 'Mélange mis à jour avec succès',
            'melange' => $melange
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3])) {
            return response(['message' => 'Action non autorisée'], 403);
        }

        $melange = MelangeProduit::find($id);

        if (!$melange) {
            return response()->json(['message' => 'Mélange non trouvé'], 404);
        }

        // Annuler les effets du mélange sur les stocks
        $this->annulerMelange($melange);

        // Supprimer le mélange
        $melange->delete();

        return response()->json([
            'message' => 'Mélange supprimé avec succès'
        ]);
    }

    /**
     * Annuler les effets d'un mélange sur les stocks
     *
     * @param  MelangeProduit  $melange
     * @return void
     */
    private function annulerMelange(MelangeProduit $melange)
    {
        // Re-créditer les stocks des produits A et B (quantités consommées)
        $stockA = Stock::where([
            'article_id' => $melange->produit_a_id,
            'lieu_stockage_id' => $melange->lieu_stockage_a_id
        ])->first();

        if ($stockA) {
            $stockA->quantite += $melange->quantite_a;
            $stockA->save();
        }

        $stockB = Stock::where([
            'article_id' => $melange->produit_b_id,
            'lieu_stockage_id' => $melange->lieu_stockage_b_id
        ])->first();

        if ($stockB) {
            $stockB->quantite += $melange->quantite_b_consommee;
            $stockB->save();
        }

        // Débiter le stock du produit B (quantité produite)
        $stockFinal = Stock::where([
            'article_id' => $melange->produit_b_id,
            'lieu_stockage_id' => $melange->lieu_stockage_final_id
        ])->first();

        if ($stockFinal) {
            $stockFinal->quantite -= $melange->quantite_b_produite;
            $stockFinal->save();
        }

        // Supprimer les mouvements d'entrée et de sortie associés
        Entrer::where('motif', 'Mélange n°' . $melange->id)->delete();
        Sortie::where('motif', 'Mélange n°' . $melange->id)->delete();
    }

    /**
     * Appliquer les effets d'un mélange sur les stocks
     *
     * @param  MelangeProduit  $melange
     * @param  \App\Models\User  $user
     * @return void
     */
    private function appliquerMelange(MelangeProduit $melange, $user)
    {
        // Récupérer l'unité de livraison du produit B
        $produitB = ArticleDepot::with('uniteLivraison')->find($melange->produit_b_id);
        $uniteId = $produitB->unite_livraison_id;

        // Débiter les stocks des produits A et B (quantités consommées)
        $stockA = Stock::firstOrCreate(
            [
                'article_id' => $melange->produit_a_id,
                'lieu_stockage_id' => $melange->lieu_stockage_a_id
            ],
            ['quantite' => 0]
        );

        $stockA->quantite -= $melange->quantite_a;
        $stockA->save();

        $stockB = Stock::firstOrCreate(
            [
                'article_id' => $melange->produit_b_id,
                'lieu_stockage_id' => $melange->lieu_stockage_b_id
            ],
            ['quantite' => 0]
        );

        $stockB->quantite -= $melange->quantite_b_consommee;
        $stockB->save();

        // Créditer le stock du produit B (quantité produite)
        $stockFinal = Stock::firstOrCreate(
            [
                'article_id' => $melange->produit_b_id,
                'lieu_stockage_id' => $melange->lieu_stockage_final_id
            ],
            ['quantite' => 0]
        );

        $stockFinal->quantite += $melange->quantite_b_produite;
        $stockFinal->save();

        $articleB = ArticleDepot::with('categorie')->where('id', $melange->produit_b_id)->firstOrFail();
        $categorieB = $articleB->categorie_id;

        // Créer les mouvements d'entrée et de sortie
        Entrer::create([
            'user_name' => $user->nom ? $user->nom : 'Système',
            'article_id' => $melange->produit_b_id,
            'categorie_article_id' => $categorieB,
            'lieu_stockage_id' => $melange->lieu_stockage_final_id,
            'unite_id' => $uniteId,
            'quantite' => $melange->quantite_b_produite,
            'entre' => now()->toDateString(),
            'motif' => 'Mélange n°' . $melange->id,
        ]);

        $articleA = ArticleDepot::with('categorie')->where('id', $melange->produit_a_id)->firstOrFail();
        $categorieA = $articleA->categorie_id;

        Sortie::create([
            'user_name' => $user->nom ? $user->nom : 'Système',
            'article_id' => $melange->produit_a_id,
            'categorie_article_id' => $categorieA,
            'lieu_stockage_id' => $melange->lieu_stockage_a_id,
            'unite_id' => $uniteId,
            'quantite' => $melange->quantite_a,
            'sortie' => now()->toDateString(),
            'motif' => 'Mélange n°' . $melange->id,
        ]);

        Sortie::create([
            'user_name' => $user->nom ? $user->nom : 'Système',
            'article_id' => $melange->produit_b_id,
            'categorie_article_id' => $categorieB,
            'lieu_stockage_id' => $melange->lieu_stockage_b_id,
            'unite_id' => $uniteId,
            'quantite' => $melange->quantite_b_consommee,
            'sortie' => now()->toDateString(),
            'motif' => 'Mélange n°' . $melange->id,
        ]);
    }

    /**
     * Récupérer le stock disponible pour un produit dans un lieu
     */
    public function getStockDisponible($produitId, $lieuStockageId)
    {
        $stock = Stock::where([
            'article_id' => $produitId,
            'lieu_stockage_id' => $lieuStockageId
        ])->first();

        return response()->json([
            'quantite_disponible' => $stock ? $stock->quantite : 0
        ]);
    }
}
