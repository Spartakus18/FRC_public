<?php

namespace App\Http\Controllers\BC;

use App\Exports\BonCommandeExport;
use App\Http\Controllers\Controller;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\AjustementStock\Stock;
use App\Models\BC\Bon_commande;
use App\Models\BC\BonCommandeProduit;
use App\Models\Parametre\CategorieArticle;
use App\Models\Parametre\Client;
use App\Models\Parametre\Destination;
use App\Models\Parametre\Unite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class Bon_commandeController extends Controller
{
    /**
     * Afficher la liste des bons de commande avec filtres et pagination.
     */
    public function index(Request $request)
    {
        $query = Bon_commande::with(['client', 'destination', 'produits.unite', 'produits.article', 'produits.lieuStockage']);

        // Filtre par recherche (numéro, désignation, client)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', '%' . $search . '%')
                    ->orWhere('designation', 'like', '%' . $search . '%')
                    ->orWhereHas('client', function ($q2) use ($search) {
                        $q2->where('nom_client', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filtre par date de début (date_BC)
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('date_BC', '>=', $request->date_start);
        }

        // Filtre par date de fin (date_BC)
        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('date_BC', '<=', $request->date_end);
        }

        // Filtre par client
        if ($request->has('client_id') && !empty($request->client_id)) {
            $query->where('client_id', $request->client_id);
        }

        // Filtre par destination
        if ($request->has('destination_id') && !empty($request->destination_id)) {
            $query->where('destination_id', $request->destination_id);
        }

        // Exclure le client "Clients divers" si demandé
        if ($request->boolean('exclude_client_divers')) {
            $query->whereHas('client', function ($q) {
                $q->where('nom_client', '!=', 'Clients divers');
            });
        }

        // Tri par date de BC décroissante (plus récent en premier)
        $query->orderBy('date_BC', 'desc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $bons = $query->paginate($perPage);

        return response()->json($bons);
    }

    /**
     * Récupérer les données nécessaires pour créer un bon de commande.
     */
    public function create()
    {
        $client = Client::all();
        $unite = Unite::all();
        // Charger les articles avec leur unité de livraison
        $categorieIds = CategorieArticle::where('nom_categorie', 'like', '%production%')->pluck('id');
        $article = ArticleDepot::with('uniteLivraison')
                                ->whereIn('categorie_id', $categorieIds)
                                ->get();
        $lieuStockage = Lieu_stockage::all();
        $destinations = Destination::all();

        return response()->json([
            'client' => $client,
            'unite' => $unite,
            'article' => $article,
            'lieuStockage' => $lieuStockage,
            'destinations' => $destinations,
        ]);
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new BonCommandeExport($request), 'bons_commandes.xlsx');
    }

    public function getStock($articleId, $lieuStockageId)
    {
        $stock = Stock::where('article_id', $articleId)
            ->where('lieu_stockage_id', $lieuStockageId)
            ->with(['articleDepot.uniteLivraison'])
            ->first();

        return response()->json([
            'quantite_disponible' => $stock ? $stock->quantite : 0,
            'unite' => $stock && $stock->articleDepot && $stock->articleDepot->uniteLivraison
                ? $stock->articleDepot->uniteLivraison->nom_unite : null,
        ]);
    }

    /**
     * Enregistrer un nouveau bon de commande.
     */
    public function store(Request $request)
    {
        $request->validate([
            'date_BC' => 'required|date',
            'client_id' => 'required|integer|exists:clients,id',
            'date_elaboration' => 'required|date',
            'designation' => 'nullable|string|max:255',
            'destination_id' => 'required|integer|exists:destinations,id',
            'date_prevu_livraison' => 'required|date|after_or_equal:date_BC',
            'observations' => 'nullable|string',
            // Validation du tableau de produits
            'produits' => 'required|array|min:1',
            'produits.*.article_id' => 'required|integer|exists:article_depots,id',
            'produits.*.lieu_stockage_id' => 'required|integer|exists:lieu_stockages,id',
            'produits.*.unite_id' => 'nullable|integer|exists:unites,id',
            'produits.*.quantite' => 'required|numeric|min:0.01',
            'produits.*.pu' => 'required|numeric|min:0',
            'produits.*.montant' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // Vérifier si le client est "Clients divers"
            $client = Client::find($request->input('client_id'));
            $clientSpecificId = $client && $client->nom_client === 'Clients divers' ? '999' : str_pad($client->id, 3, '0', STR_PAD_LEFT);

            // Récupérer l'année et le mois de la date_BC
            $currentYear  = date('Y', strtotime($request->date_BC));
            $currentMonth = date('m', strtotime($request->date_BC));

            // Séquence globle annuelle
            $globalCount = Bon_commande::whereYear('date_BC', $currentYear)->count() + 1;
            $globalNumber = str_pad($globalCount, 3, '0', STR_PAD_LEFT);

            // Format : YYYY-MM-CID-SEQ
            $numero = $currentYear . '-' . $currentMonth . '-' . $clientSpecificId . '-' . $globalNumber;

            // Création du bon de commande
            $bon = Bon_commande::create([
                'numero' => $numero,
                'date_BC' => $request->date_BC,
                'client_id' => $request->client_id,
                'date_elaboration' => $request->date_elaboration,
                'destination_id' => $request->destination_id,
                'designation' => $request->designation,
                'date_prevu_livraison' => $request->date_prevu_livraison,
                'observations' => $request->observations,
            ]);

            // Enregistrement des produits
            foreach ($request->produits as $prod) {
                BonCommandeProduit::create([
                    'bon_commande_id' => $bon->id,
                    'article_id' => $prod['article_id'],
                    'lieu_stockage_id' => $prod['lieu_stockage_id'],
                    'unite_id' => $prod['unite_id'],
                    'quantite' => $prod['quantite'],
                    'pu' => $prod['pu'],
                    'montant' => $prod['montant'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Bon de commande créé avec succès',
                'data' => $bon->load('produits.article', 'produits.unite', 'produits.lieuStockage', 'destination'),
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Afficher un bon de commande spécifique.
     */
    public function show($id)
    {
        $bon = Bon_commande::with([
            'client',
            'destination',
            'produits.unite',
            'produits.article',
            'produits.lieuStockage'
        ])->find($id);

        if (!$bon) {
            return response()->json(['message' => 'Bon de commande non trouvé'], 404);
        }

        return response()->json($bon);
    }

    /**
     * Récupérer un bon de commande pour édition.
     */
    public function edit($id)
    {
        $bon = Bon_commande::with(['destination'])->find($id);

        if (!$bon) {
            return response()->json(['message' => 'Bon de commande non trouvé'], 404);
        }

        return response()->json($bon);
    }

    /**
     * Mettre à jour un bon de commande existant.
     */
    public function update(Request $request, $id)
    {
        // Validation des données
        $validate = $request->validate([
            'date_BC' => 'required|date',
            'client_id' => 'required|integer|exists:clients,id',
            'date_elaboration' => 'required|date',
            'designation' => 'nullable|string|max:255',
            'destination_id' => 'required|integer|exists:destinations,id',
            'date_prevu_livraison' => 'required|date|after_or_equal:date_BC',
            'observations' => 'nullable|string',
            'produits' => 'required|array|min:1',
            'produits.*.article_id' => 'required|integer|exists:article_depots,id',
            'produits.*.lieu_stockage_id' => 'required|integer|exists:lieu_stockages,id',
            'produits.*.unite_id' => 'nullable|integer|exists:unites,id',
            'produits.*.quantite' => 'required|numeric|min:0.01',
            'produits.*.pu' => 'required|numeric|min:0',
            'produits.*.montant' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // Récupération du bon existant
            $bonCommande = Bon_commande::findOrFail($id);
            $ancienClientId = $bonCommande->client_id;
            $nouveauClientId = $request->input('client_id');

            // Vérifier si le client est "Clients divers"
            $client = Client::find($nouveauClientId);
            $clientSpecificId = $client && $client->nom_client === 'Clients divers' ? '999' : str_pad($client->id, 3, '0', STR_PAD_LEFT);

            // Récupérer l'année et le mois de la date_BC
            $currentYear  = date('Y', strtotime($request->date_BC));
            $currentMonth = date('m', strtotime($request->date_BC));

            // Séquence globle annuelle
            $globalCount = Bon_commande::whereYear('date_BC', $currentYear)
                ->where('id', '!=', $id)
                ->count() + 1;
            $globalNumber = str_pad($globalCount, 3, '0', STR_PAD_LEFT);

            // Format : YYYY-MM-CID-SEQ
            $numero = $currentYear . '-' . $currentMonth . '-' . $clientSpecificId . '-' . $globalNumber;

            // Mise à jour des infos principales
            $bonCommande->update([
                'numero' => $numero,
                'date_BC' => $validate['date_BC'],
                'client_id' => $validate['client_id'],
                'date_elaboration' => $validate['date_elaboration'],
                'destination_id' => $validate['destination_id'],
                'designation' => $validate['designation'],
                'date_prevu_livraison' => $validate['date_prevu_livraison'],
                'observations' => $validate['observations'],
            ]);

            // Suppression des anciens produits
            $bonCommande->produits()->delete();

            // Ajout des nouveaux produits
            foreach ($validate['produits'] as $produit) {
                $bonCommande->produits()->create([
                    'article_id' => $produit['article_id'],
                    'lieu_stockage_id' => $produit['lieu_stockage_id'],
                    'unite_id' => $produit['unite_id'],
                    'quantite' => $produit['quantite'],
                    'pu' => $produit['pu'],
                    'montant' => $produit['montant'],
                ]);
            }

            DB::commit();

            // Chargement des relations pour la réponse
            $bonCommande->load([
                'client',
                'destination',
                'produits.article',
                'produits.lieuStockage',
                'produits.unite',
            ]);

            return response()->json([
                'message' => 'Bon de commande mis à jour avec succès',
                'bon_commande' => $bonCommande,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Supprimer un bon de commande.
     */
    public function destroy($id)
    {
        $bon = Bon_commande::find($id);

        if (!$bon) {
            return response()->json(['message' => 'Bon de commande non trouvé'], 404);
        }

        $bon->delete();

        return response()->json(['message' => 'Bon de commande supprimé avec succès']);
    }
}