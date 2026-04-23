<?php

namespace App\Http\Controllers\Materiel;

use App\Exports\MaterielExport;
use App\Http\Controllers\Controller;
use App\Models\Parametre\Materiel;
use App\Models\User;
use App\Notifications\GasoilSeuilAtteint;
use App\Services\GasoilConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class MaterielController extends Controller
{
    public function index(Request $request)
    {
        $query = Materiel::query();

        // Filtre pour recherche
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('nom_materiel', 'like', '%' . $search . '%');
        }

        // filtre par catégorie
        if ($request->has('categorie') && !empty($request->categorie)) {
            $query->where('categorie', $request->categorie);
        }

        // filtre par status
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Tri par défaut
        /* $query->orderBy('created_at', 'desc'); */
        $query->orderBy('nom_materiel', 'desc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $materiels = $query->paginate($perPage);

        /* foreach ($materiels as $materiel) {
            if ($materiel->actuelGasoil <= $materiel->seuil && !$materiel->seuil_notified) {
                event(new GasoilSeuilAtteint($materiel));
                $materiel->update(['seuil_notified' => true]);
            }
        } */
        return response()->json($materiels, 200);
    }


    public function getMaterielGasoil()
    {
        try {
            $materiels = Materiel::all();
            return $materiels;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des matériels',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);
        }
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new MaterielExport($request), 'materiels.xlsx');
    }

    public function getMetaInfo($id)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->role_id === 1;

        // Charger le matériel avec ses relations en utilisant les noms exacts de votre modèle
        $materiel = Materiel::with([
            'gasoilsCible' => function ($query) {
                $query->with(['source', 'materielSource'])
                    ->orderBy('created_at', 'desc')
                    ->limit(3);
            },
            'huilesCible' => function ($query) {
                $query->with(['articleDepot', 'sourceLieuStockage', 'subdivisionCible'])
                    ->orderBy('created_at', 'desc')
                    ->limit(3);
            },
            'pneus' => function ($query) {
                $query->where('situation', 'en_service');
            }
        ])->find($id);

        if (!$materiel) {
            return response()->json([
                'success' => false,
                'message' => 'Matériel introuvable'
            ], 404);
        }

        // Transformer les données gasoil
        $derniersGasoils = $materiel->gasoilsCible->map(function ($gasoil) use ($isAdmin) {
            return [
                'id' => $gasoil->id,
                'date' => $gasoil->created_at->format('d/m/Y H:i'),
                'quantite' => $gasoil->quantite,
                'source' => $gasoil->source_station ? 'Station' : ($gasoil->source ? $gasoil->source->nom : 'N/A'),
                'prix_total' => $isAdmin ? $gasoil->prix_total : null,
                'type_operation' => $gasoil->type_operation,
                'ajouter_par' => $gasoil->ajouter_par,
            ];
        });

        // Transformer les données huile
        $dernieresHuiles = $materiel->huilesCible->map(function ($huile) use ($isAdmin) {
            return [
                'id' => $huile->id,
                'date' => $huile->created_at->format('d/m/Y H:i'),
                'quantite' => $huile->quantite,
                'article' => $huile->articleDepot->nom_article ?? 'N/A',
                'source' => $huile->source_station ?
                    ($huile->source_station === 'station' ? 'Station' : 'Autre') : ($huile->sourceLieuStockage->nom ?? 'N/A'),
                'prix_total' => $isAdmin ? $huile->prix_total : null,
                'ajouter_par' => $huile->ajouter_par,
                'subdivision' => $huile->subdivisionCible->nom_subdivision ?? 'N/A'
            ];
        });

        // Transformer les données pneus
        $pneusInstallés = $materiel->pneus->map(function ($pneu) {
            return [
                'id' => $pneu->id,
                'num_serie' => $pneu->num_serie,
                'marque' => $pneu->marque,
                'type' => $pneu->type,
                'etat' => $pneu->etat,
                'caracteristiques' => $pneu->caracteristiques,
                'date_mise_en_service' => $pneu->date_mise_en_service ?
                    \Carbon\Carbon::parse($pneu->date_mise_en_service)->format('d/m/Y') : 'N/A',
                'kilometrage' => $pneu->kilometrage,
                'observations' => $pneu->observations,
                'emplacement' => $pneu->emplacement
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'materiel' => [
                    'id' => $materiel->id,
                    'nom' => $materiel->nom_materiel,
                    'categorie' => $materiel->categorie,
                    'gasoil_actuel' => $materiel->actuelGasoil,
                    'seuil_gasoil' => $materiel->seuil,
                    'capaciteL' => $materiel->capaciteL, // Ajout de capaciteL
                    'capaciteCm' => $materiel->capaciteCm, // Ajout de capaciteCm
                    'statut' => $materiel->status ? 'Disponible' : 'Indisponible',
                    'nbr_pneu' => $materiel->nbr_pneu ? $materiel->nbr_pneu : 'N/A',
                    'consommation_horaire' => $materiel->consommation_horaire ? $materiel->consommation_horaire : 'N/A',
                    'compteur_actuel' => $materiel->compteur_actuel ? $materiel->compteur_actuel : 'N/A'
                ],
                'derniers_gasoils' => $derniersGasoils,
                'dernieres_huiles' => $dernieresHuiles,
                'pneus_installes' => $pneusInstallés
            ]
        ], 200);
    }

    /**
     * Créer un nouveau matériel
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom_materiel' => 'required|string|unique:materiels,nom_materiel',
            'status'       => 'required|boolean',
            'categorie'    => 'required|in:groupe,vehicule,engin',
            'nbr_pneu'     => 'nullable|integer|min:4|max:22|required_if:categorie,vehicule',
            'capaciteL'     => 'nullable|numeric|min:0',
            'capaciteCm'     => 'nullable|numeric|min:0',
            'consommation_horaire' => 'nullable|numeric|min:0',
            'compteur_actuel' => 'nullable|numeric|min:0',
            'seuil'        => 'nullable|numeric|min:0',
            'actuelGasoil' => 'nullable|numeric|min:0',
        ], [
            'nom_materiel.required' => 'Le nom du matériel est obligatoire.',
            'nom_materiel.unique'   => 'Ce nom de matériel existe déjà.',
            'nbr_pneu.required_if'  => 'Le nombre de pneus est obligatoire pour un véhicule ou un engin.',
            'nbr_pneu.min'          => 'Un véhicule ou un engin doit avoir au minimum 4 pneus.',
            'nbr_pneu.max'          => 'Un véhicule ou un engin ne peut pas dépasser 22 pneus.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $materiel = Materiel::create($validator->validated());

            // mettre a jour la capaciter du matériel REF_LITERS (actuellement 20)
            $materiel->capaciteL = GasoilConversionService::REF_LITERS;
            $materiel->save();

            return response()->json([
                'success' => true,
                'message' => 'Matériel créé avec succès',
                'data'    => $materiel
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du matériel',
                'error'   => env('APP_DEBUG') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);
        }
    }

    /**
     * Afficher un matériel spécifique
     */
    public function show($id)
    {
        $materiel = Materiel::find($id);

        if (!$materiel) {
            return response()->json([
                'success' => false,
                'message' => 'Matériel introuvable'
            ], 404);
        }

        return response()->json($materiel, 200);
    }

    /**
     * Mettre à jour un matériel
     */
    public function update(Request $request, $id)
    {
        $materiel = Materiel::find($id);
        $admin = User::whereHas('role', function ($query) {
            $query->where('id', 1);
        })->first();

        if (!$materiel) {
            return response()->json([
                'success' => false,
                'message' => 'Matériel introuvable'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom_materiel' => 'required|string|unique:materiels,nom_materiel,' . $id,
            'status'       => 'required|boolean',
            'categorie'    => 'required|in:groupe,vehicule,engin',
            'nbr_pneu'     => 'nullable|integer|min:4|max:22|required_if:categorie,vehicule',
            'capaciteL'     => 'nullable|numeric|min:0',
            'consommation_horaire' => 'nullable|numeric|min:0',
            'compteur_actuel' => 'nullable|numeric|min:0',
            'capaciteCm'     => 'nullable|numeric|min:0',
            'seuil'        => 'nullable|numeric|min:0',
            'actuelGasoil' => 'nullable|numeric|min:0',
        ], [
            'nom_materiel.required' => 'Le nom du matériel est obligatoire.',
            'nbr_pneu.required_if'  => 'Le nombre de pneus est obligatoire pour un véhicule.',
            'nbr_pneu.min'          => 'Un véhicule doit avoir au minimum 4 pneus.',
            'nbr_pneu.max'          => 'Un véhicule ne peut pas dépasser 22 pneus.',
            'status.required' => 'Le status est obligatoire'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors()
            ], 422);
        }

        $materiel->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Matériel mis à jour avec succès',
            'data'    => $materiel
        ], 200);
    }

    /**
     * Mettre à jour uniquement le compteur actuel d'un matériel
     */
    public function updateCompteurActuel(Request $request, $id)
    {
        $materiel = Materiel::find($id);

        if (!$materiel) {
            return response()->json([
                'success' => false,
                'message' => 'Matériel introuvable'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'compteur_actuel' => 'required|numeric|min:0',
        ], [
            'compteur_actuel.required' => 'Le compteur actuel est obligatoire.',
            'compteur_actuel.numeric' => 'Le compteur actuel doit être un nombre.',
            'compteur_actuel.min' => 'Le compteur actuel ne peut pas être négatif.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors()
            ], 422);
        }

        $materiel->update([
            'compteur_actuel' => $validator->validated()['compteur_actuel'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Compteur actuel mis à jour avec succès',
            'data' => [
                'id' => $materiel->id,
                'nom_materiel' => $materiel->nom_materiel,
                'compteur_actuel' => $materiel->compteur_actuel,
            ]
        ], 200);
    }

    /**
     * Supprimer un matériel
     */
    public function destroy($id)
    {
        $materiel = Materiel::find($id);

        if (!$materiel) {
            return response()->json([
                'success' => false,
                'message' => 'Matériel introuvable'
            ], 404);
        }

        $materiel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Matériel supprimé avec succès'
        ], 200);
    }
}
