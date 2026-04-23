<?php

namespace App\Http\Controllers\BC\Huile;

use App\Http\Controllers\Controller;
use App\Http\Requests\BC\Huile\BonHuileRequest;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Stock;
use App\Models\BC\BonHuile;
use App\Models\Consommable\Huile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BonHuileController extends Controller
{

    /* Générer un numéro de bon */
    public function generateBon($type_bon)
    {
        $num_bon = BonHuile::getNextAvailableNumber($type_bon);
        return response()->json(['num_bon' => $num_bon], 201);
    }
    /**
     * Liste des bons d'huile
     */
    public function index(Request $request)
    {
        $query = BonHuile::with([
            'materiel',
            'subdivision',
            'article_versement',
            'huile.materielSource',
            'huile.materielCible',
            'huile.subdivisionCible',
            'huile.articleDepot',
            'huile.sourceLieuStockage'
        ]);

        // Filtre par recherche (numéro de bon, matériel ou subdivision)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('num_bon', 'like', '%' . $search . '%')
                    ->orWhereHas('materiel', function ($q) use ($search) {
                        $q->where('nom_materiel', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('subdivision', function ($q) use ($search) {
                        $q->where('nom', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('histHuile', function ($q) use ($search) {
                        $q->whereHas('materielCible', function ($q2) use ($search) {
                            $q2->where('nom_materiel', 'like', '%' . $search . '%');
                        });
                    });
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

        // Tri par défaut par date de création décroissante
        $query->orderBy('created_at', 'desc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $bonHuiles = $query->paginate($perPage);

        return response()->json($bonHuiles);
    }

    /**
     * Ajout de bons d'huile (support operation multiple)
     */
    public function store(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'num_bon' => 'required|string|max:255',
            'type_bon' => 'required|string|in:approStock,achat,transfert',
            'source' => 'required|exists:lieu_stockages,id',
            'operations' => 'required|array|min:1',
            'operations.*.materiel_id' => 'required|exists:materiels,id',
            'operations.*.subdivision_id' => 'required|exists:subdivisions,id',
            'operations.*.quantite' => 'required|numeric|min:0.01',
            'operations.*.article_versement_id' => 'required|exists:article_depots,id',
        ]);

        DB::beginTransaction();

        try {
            // VÉRIFICATION DES STOCKS
            // 1. Regrouper les opérations par article pour calculer les totaux par article
            $quantitesParArticle = [];

            foreach ($validated['operations'] as $operation) {
                $articleId = $operation['article_versement_id'];

                if (!isset($quantitesParArticle[$articleId])) {
                    $quantitesParArticle[$articleId] = 0;
                }

                $quantitesParArticle[$articleId] += $operation['quantite'];
            }

            // 2. Vérifier pour chaque article si le stock est suffisant
            foreach ($quantitesParArticle as $articleId => $quantiteTotaleDemandee) {
                // Récupérer le stock disponible pour cet article dans le lieu source
                $stockDisponible = Stock::where('lieu_stockage_id', $validated['source'])
                    ->where('article_id', $articleId)
                    ->sum('quantite');

                if ($quantiteTotaleDemandee > $stockDisponible) {
                    // Récupérer le nom de l'article pour le message d'erreur
                    $article = ArticleDepot::find($articleId);
                    $articleNom = $article ? $article->nom_article : 'Article inconnu';

                    DB::rollBack();

                    return response()->json([
                        'message' => 'Erreur de validation des stocks',
                        'error' => "Quantité insuffisante pour l'article '$articleNom'. Demande: $quantiteTotaleDemandee L, Disponible: $stockDisponible L"
                    ], 422);
                }
            }

            // Calcul quantité totale
            $quantiteTotale = collect($validated['operations'])
                ->sum('quantite');

            // Création du BON
            $bon = BonHuile::create([
                'num_bon' => $validated['num_bon'],
                'source_lieu_stockage_id' => $validated['source'],
                'ajouter_par' => auth()->user()->nom ?? 'system',
            ]);

            // Création des opérations HUILES
            foreach ($validated['operations'] as $operation) {
                Huile::create([
                    'bon_id' => $bon->id,
                    'type_operation' => 'versement',

                    // Source
                    'source_lieu_stockage_id' => $validated['source'],

                    // Destination
                    'materiel_id_cible' => $operation['materiel_id'],
                    'subdivision_id_cible' => $operation['subdivision_id'],

                    // Article
                    'article_versement_id' => $operation['article_versement_id'],

                    // Quantité
                    'quantite' => $operation['quantite'],

                    // Audit
                    'ajouter_par' => auth()->user()->nom ?? 'system',
                ]);
            }

            // METTRE À JOUR LES STOCKS
            /* foreach ($quantitesParArticle as $articleId => $quantiteTotaleDemandee) {
                // Chercher le stock à mettre à jour
                $stock = Stock::where('lieu_stockage_id', $validated['source'])
                    ->where('article_id', $articleId)
                    ->first();

                if ($stock) {
                    $stock->quantite -= $quantiteTotaleDemandee;

                    // Si la quantité devient négative, c'est anormal (déjà vérifié)
                    if ($stock->quantite < 0) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Erreur: quantité négative dans le stock'
                        ], 500);
                    }

                    $stock->save();
                }
            } */

            DB::commit();

            return response()->json([
                'message' => 'Bon d\'huile créé avec succès',
                'data' => $bon->load('huile')
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'message' => 'Erreur lors de la création du bon d\'huile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mise à jour d'un bon d'huile
     */
    public function update(BonHuileRequest $request, BonHuile $bonHuile)
    {
        $data = $request->validated()[0]; // Prend le premier élément pour la mise à jour unique
        $data['modifier_par'] = auth()->user()->nom;

        $bonHuile->update($data);
        $bonHuile->load(['materiel', 'subdivision', 'article_versement']);

        return response()->json([
            'message' => 'Bon d\'huile modifié avec succès',
            'data' => $bonHuile
        ]);
    }

    /**
     * Mise à jour d'une opération d'huile
     */
    public function updateHuile(Request $request, $id)
    {
        // Validation des données
        $validated = $request->validate([
            'quantite' => 'sometimes|required|numeric|min:0.01',
            'materiel_id_cible' => 'sometimes|required|exists:materiels,id',
            'subdivision_id_cible' => 'sometimes|required|exists:subdivisions,id',
            'article_versement_id' => 'sometimes|required|exists:article_depots,id',
        ]);

        DB::beginTransaction();

        try {
            // Récupérer l'opération d'huile
            $huile = Huile::findOrFail($id);

            // Vérifier que l'opération n'a pas encore été confirmée
            if ($huile->is_consumed) {
                // Ajouter l'information de modification
                $validated['modifier_par'] = auth()->user()->nom ?? 'system';
                // Mettre à jour l'opération
                $huile->update($validated);
                // Recharger les relations
                $huile->load([
                    'materielCible',
                    'subdivisionCible',
                    'articleDepot',
                    'sourceLieuStockage'
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'Opération d\'huile modifiée avec succès',
                    'data' => $huile
                ], 200);
            }

            // Vérifier le stock si la quantité ou l'article a changé
            if (isset($validated['quantite']) || isset($validated['article_versement_id'])) {
                $newQuantite = $validated['quantite'] ?? $huile->quantite;
                $newArticleId = $validated['article_versement_id'] ?? $huile->article_versement_id;
                $sourceLieuId = $validated['source_lieu_stockage_id'] ?? $huile->source_lieu_stockage_id;

                // Calculer la différence si c'est le même article
                if ($newArticleId == $huile->article_versement_id) {
                    $difference = $newQuantite - $huile->quantite;

                    if ($difference > 0) {
                        // On augmente la quantité, vérifier le stock
                        $stockDisponible = Stock::where('lieu_stockage_id', $sourceLieuId)
                            ->where('article_id', $newArticleId)
                            ->sum('quantite');

                        if ($difference > $stockDisponible) {
                            $article = ArticleDepot::find($newArticleId);
                            $articleNom = $article ? $article->nom_article : 'Article inconnu';

                            DB::rollBack();

                            return response()->json([
                                'message' => 'Erreur de validation des stocks',
                                'error' => "Quantité insuffisante pour l'article '$articleNom'. Augmentation demandée: $difference L, Disponible: $stockDisponible L"
                            ], 422);
                        }
                    }
                } else {
                    // Changement d'article, vérifier le stock du nouvel article
                    $stockDisponible = Stock::where('lieu_stockage_id', $sourceLieuId)
                        ->where('article_id', $newArticleId)
                        ->sum('quantite');

                    if ($newQuantite > $stockDisponible) {
                        $article = ArticleDepot::find($newArticleId);
                        $articleNom = $article ? $article->nom_article : 'Article inconnu';

                        DB::rollBack();

                        return response()->json([
                            'message' => 'Erreur de validation des stocks',
                            'error' => "Quantité insuffisante pour l'article '$articleNom'. Demande: $newQuantite L, Disponible: $stockDisponible L"
                        ], 422);
                    }
                }
            }

            // Ajouter l'information de modification
            $validated['modifier_par'] = auth()->user()->nom ?? 'system';

            // Mettre à jour l'opération
            $huile->update($validated);

            // Recharger les relations
            $huile->load([
                'materielCible',
                'subdivisionCible',
                'articleDepot',
                'sourceLieuStockage'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Opération d\'huile modifiée avec succès',
                'data' => $huile
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'message' => 'Erreur lors de la modification de l\'opération d\'huile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suppression d'un bon d'huile
     */
    public function destroy(BonHuile $bonHuile)
    {
        if (!$bonHuile) {
            return response()->json([
                "message" => "Bon d'huile non trouver"
            ], 500);
        }

        $bonHuile->delete();

        return response()->json([
            'message' => 'Bon d\'huile supprimé avec succès'
        ]);
    }
}
