<?php

namespace App\Http\Controllers\BC\Gasoil;

use App\Http\Controllers\Controller;
use App\Http\Requests\BC\Gasoil\BonGasoilRequest;
use App\Models\BC\BonGasoil;
use App\Models\Consommable\Gasoil;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Trunc;

class BonGasoilController extends Controller
{

    /* Génerer un numéro de bon */
    public function generateBon($type_bon)
    {
        $num_bon = BonGasoil::getNextAvailableNumber($type_bon);
        return response()->json(['num_bon' => $num_bon], 201);
    }

    /**
     * Liste des bons de gasoil
     */
    public function index(Request $request)
    {
        $query = BonGasoil::with([
            'gasoil' => function ($q) {
                $q->with([
                    'materielCible:id,nom_materiel,actuelGasoil,capaciteCm',
                    'materielSource:id,nom_materiel,actuelGasoil,capaciteCm',
                    'source:id,nom'
                ]);
            },
            'sourceStockage:id,nom'
        ]);

        /* $query->whereDate('created_at', '<', Carbon::today()); */
        $query->orderBy('created_at', 'desc');

        // Filtre par recherche (numéro de bon)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('num_bon', 'like', '%' . $search . '%')
                    ->orWhereHas('gasoil.materielCible', function ($q) use ($search) {
                        $q->where('nom_materiel', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('gasoil.materielSource', function ($q) use ($search) {
                        $q->where('nom_materiel', 'like', '%' . $search . '%');
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
        $bonGasoils = $query->paginate($perPage);

        return response()->json($bonGasoils);
    }

    /**
     * Récuperer les bon gasoils avec ces operation
     */
    public function bonGasoilWithGasoil(Request $request): JsonResponse
    {
        // Récupérer la date d'aujourd'hui
        $today = Carbon::today();
        // Filtrer les bons créés aujourd'hui
        $bonsGasoil = BonGasoil::whereDate('created_at', $today)
            ->with(['gasoil.materielCible', 'sourceStockage', 'gasoil.materielSource'])
            ->get();

        return response()->json($bonsGasoil);
    }

    /**
     * Ajout de bons de gasoil (support operation multiple)
     */
    public function store(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'num_bon' => 'required|string|max:255',
            'type_bon' => 'required|string|in:approStock,achat,transfert',
            'source' => 'nullable|exists:lieu_stockages,id',
            'operations' => 'required|array|min:1',
            'operations.*.materiel_id' => 'required|exists:materiels,id',
            'operations.*.quantite' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();

        try {
            // Calcul quantité totale
            $quantiteTotale = collect($validated['operations'])
                ->sum('quantite');

            // Création du BON
            $bon = BonGasoil::create([
                'num_bon' => $validated['num_bon'],
                'quantite' => $quantiteTotale,
                'source_lieu_stockage_id' => $validated['source'],
                'ajouter_par' => auth()->user()->nom ?? 'system',
            ]);

            // Création des opérations GASOIL
            foreach ($validated['operations'] as $operation) {
                Gasoil::create([
                    'bon_id' => $bon->id,
                    'type_operation' => 'versement',

                    // Source
                    'source_lieu_stockage_id' => $validated['source'],
                    'source_station' => $validated['source'] ? null : 'station',

                    // Cible
                    'materiel_id_cible' => $operation['materiel_id'],

                    // Quantité
                    'quantite' => $operation['quantite'],

                    // Audit
                    'ajouter_par' => auth()->user()->nom ?? 'system',

                    // Métier
                    'is_consumed' => false,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Bon de gasoil créé avec succès',
                'data' => $bon->load('gasoil')
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'message' => 'Erreur lors de la création du bon de gasoil',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Mise à jour d'un bon de gasoil
     */
    public function update(BonGasoilRequest $request, BonGasoil $bonGasoil)
    {
        $data = $request->validated()[0]; // Prend le premier élément pour la mise à jour unique
        $data['modifier_par'] = auth()->user()->nom;

        $bonGasoil->update($data);
        return response()->json([
            'message' => 'Bon de gasoil modifié avec succès',
            'data' => $bonGasoil
        ]);
    }

    /**
     * Suppression d'un bon de gasoil
     */
    public function destroy(BonGasoil $bonGasoil)
    {
        $bonGasoil->delete();

        if (!$bonGasoil) {
            return response()->json([
                'message' => "Bon gasoil non trouver"
            ], 500);
        }

        return response()->json([
            'message' => 'Bon de gasoil supprimé avec succès'
        ]);
    }

    public function getBon(Request $request)
    {
        $query = BonGasoil::with('materiel')
            ->where('is_consumed', false);

        // Filtre par recherche sur le numéro de bon
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('num_bon', 'like', '%' . $search . '%');
        }

        // Limite les résultats pour l'autocomplétion
        $query->orderBy('num_bon', 'asc');
        $bons = $query->limit(50)->get();

        return response()->json([
            'data' => $bons->map(function ($bon) {
                return [
                    'id' => $bon->id,
                    'num_bon' => $bon->num_bon,
                    'materiel' => $bon->materiel ? $bon->materiel->nom_materiel : 'N/A',
                    'quantite' => $bon->quantite,
                    'created_at' => $bon->created_at->format('d/m/Y'),
                ];
            })
        ]);
    }
}
