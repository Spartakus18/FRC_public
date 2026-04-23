<?php

namespace App\Http\Controllers\Produit;

use App\Exports\VentesExport;
use App\Http\Controllers\Controller;
use App\Models\Produit\Vente;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class VenteController extends Controller
{
    /**
     * Display a listing of the resource with filters and pagination.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Vente::with([
            'client',
            'vehicule',
            'chauffeur',
            'produit',
            'produit.articleDepot',
            'produit.lieuStockage',
            'bonLivraison',
            'bonLivraison.bonCommandeProduit',
            'bonLivraison.bonCommandeProduit.unite',
        ]);

        // Filtre par recherche (observation, destination, client, produit)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('observation', 'like', '%' . $search . '%')
                    ->orWhere('destination', 'like', '%' . $search . '%')
                    ->orWhereHas('client', function ($q2) use ($search) {
                        $q2->where('nom_client', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('produit.articleDepot', function ($q3) use ($search) {
                        $q3->where('nom_article', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('bonLivraison', function ($q3) use ($search) {
                        $q3->where('numBL', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filtre par date de début (date)
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('date', '>=', $request->date_start);
        }

        // Filtre par date de fin (date)
        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('date', '<=', $request->date_end);
        }

        // Filtre par client
        if ($request->has('client_id') && !empty($request->client_id)) {
            $query->where('client_id', $request->client_id);
        }

        // Filtre par produit
        if ($request->has('produit_id') && !empty($request->produit_id)) {
            $query->where('produit_id', $request->produit_id);
        }

        // Filtre par véhicule
        if ($request->has('vehicule_id') && !empty($request->vehicule_id)) {
            $query->where('materiel_id', $request->vehicule_id);
        }

        // Filtre par chauffeur
        if ($request->has('chauffeur_id') && !empty($request->chauffeur_id)) {
            $query->where('chauffeur_id', $request->chauffeur_id);
        }

        // Filtre par bon de livraison
        if ($request->has('bl_id') && !empty($request->bl_id)) {
            $query->where('bl_id', $request->bl_id);
        }

        // Exclure le client "Clients divers" si demandé
        if ($request->boolean('exclude_client_divers')) {
            $query->whereHas('client', function ($q) {
                $q->where('nom_client', '!=', 'Clients divers');
            });
        }

        // Tri par date décroissante (plus récent en premier)
        $query->orderBy('date', 'desc')->orderBy('heure', 'desc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $ventes = $query->paginate($perPage);

        return response()->json($ventes);
    }

    public function exportExcel(Request $request)
    {
        $filters = $request->all(); // Récupère tous les filtres envoyés depuis le front
        return Excel::download(new VentesExport($filters), 'ventes.xlsx');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Les ventes sont généralement créées automatiquement via les BL
        // Cette méthode peut être utilisée pour des ventes directes si nécessaire
        return response()->json([
            'message' => 'Les ventes sont généralement créées automatiquement via les bons de livraison.'
        ], 400);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $vente = Vente::with([
            'client',
            'vehicule',
            'chauffeur',
            'produit',
            'produit.articleDepot',
            'produit.lieuStockage',
            'bonLivraison'
        ])->find($id);

        if (!$vente) {
            return response()->json([
                'message' => 'Vente non trouvée'
            ], 404);
        }

        return response()->json($vente);
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
        // Les ventes étant liées aux BL, la mise à jour directe est déconseillée
        // Il vaut mieux modifier le BL associé
        return response()->json([
            'message' => 'La modification directe des ventes est déconseillée. Modifiez le bon de livraison associé.'
        ], 400);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // La suppression des ventes est délicate car liée à la logique métier
        // Il vaut mieux supprimer le BL associé
        return response()->json([
            'message' => 'La suppression directe des ventes est déconseillée. Supprimez le bon de livraison associé.'
        ], 400);
    }

    /**
     * Récupérer les statistiques des ventes
     */
    public function stats(Request $request)
    {
        $query = Vente::query();

        // Filtres par date
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('date', '>=', $request->date_start);
        }

        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('date', '<=', $request->date_end);
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_ventes,
            SUM(quantite) as quantite_totale,
            COUNT(DISTINCT client_id) as clients_differents,
            COUNT(DISTINCT produit_id) as produits_differents
        ')->first();

        return response()->json($stats);
    }

    /**
     * Récupérer les données pour les filtres
     */
    public function filterData()
    {
        // Ces données devraient être récupérées depuis leurs contrôleurs respectifs
        // Pour l'instant, on retourne un message indiquant comment procéder
        return response()->json([
            'message' => 'Utilisez les endpoints spécifiques pour chaque type de données',
            'endpoints' => [
                'clients' => '/api/clients',
                'produits' => '/api/articles',
                'vehicules' => '/api/materiels',
                'chauffeurs' => '/api/conducteurs',
                'bons_livraison' => '/api/bon-livraisons'
            ]
        ]);
    }

    /**
     * Historique des ventes par client
     */
    public function byClient($clientId, Request $request)
    {
        $query = Vente::with([
            'vehicule',
            'chauffeur',
            'produit',
            'produit.articleDepot',
            'bonLivraison'
        ])->where('client_id', $clientId);

        // Filtres par date
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('date', '>=', $request->date_start);
        }

        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('date', '<=', $request->date_end);
        }

        $query->orderBy('date', 'desc');

        $perPage = $request->per_page ?? 10;
        $ventes = $query->paginate($perPage);

        return response()->json($ventes);
    }

    /**
     * Historique des ventes par produit
     */
    public function byProduit($produitId, Request $request)
    {
        $query = Vente::with([
            'client',
            'vehicule',
            'chauffeur',
            'produit.articleDepot',
            'bonLivraison'
        ])->where('produit_id', $produitId);

        // Filtres par date
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('date', '>=', $request->date_start);
        }

        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('date', '<=', $request->date_end);
        }

        $query->orderBy('date', 'desc');

        $perPage = $request->per_page ?? 10;
        $ventes = $query->paginate($perPage);

        return response()->json($ventes);
    }
}