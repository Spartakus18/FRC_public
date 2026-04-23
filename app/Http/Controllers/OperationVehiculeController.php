<?php

namespace App\Http\Controllers;

use App\Models\Location\AideChauffeur;
use App\Models\Location\Conducteur;
use App\Models\OperationVehicule;
use App\Models\Parametre\Materiel;
use App\Models\Produit\Categorie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OperationVehiculeController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Valider les données
            $validatedData = OperationVehicule::validateOperationData($request->all());
            /* Log::debug('Store OperationVehicule', [
                'code' => 'ici'
            ]); */
            // Créer l'opération
            $operation = OperationVehicule::create($validatedData);

            // Calculer les consommations
            $destinationId = $request->input('destination_id');
            $calculs = $operation->calculerTousLesCalculs($validatedData, $destinationId);

            return response()->json([
                'success' => true,
                'message' => 'Opération créée avec succès',
                'operation' => $operation,
                'calculs' => $calculs
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'opération',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $operation = OperationVehicule::findOrFail($id);

            // Valider les données pour la mise à jour
            $validatedData = OperationVehicule::validateOperationData($request->all(), true, $id);

            // Mettre à jour l'opération
            $operation->update($validatedData);

            // Recalculer les consommations si nécessaire
            if ($request->hasAny(['gasoil_depart', 'gasoil_arrive', 'heure_depart', 'heure_arrive'])) {
                $destinationId = $request->input('destination_id');
                $calculs = $operation->calculerTousLesCalculs($validatedData, $destinationId);
            }

            return response()->json([
                'success' => true,
                'message' => 'Opération mise à jour avec succès',
                'operation' => $operation,
                'calculs' => $calculs ?? null
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'opération',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $materiel = Materiel::all();
        $chauffeur = Conducteur::all();
        $aideChauffeur = AideChauffeur::all();
        $categorie_travail = Categorie::all();
        return response()->json([
            'materiel' => $materiel,
            'chauffeur' => $chauffeur,
            'AideChauffeur' => $aideChauffeur,
            'categorie_travail' => $categorie_travail
        ]);
    }
}
