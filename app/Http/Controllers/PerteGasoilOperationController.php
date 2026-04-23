<?php

namespace App\Http\Controllers;

use App\Models\Parametre\Materiel;
use App\Models\PerteGasoilOperation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PerteGasoilOperationController extends Controller
{
    /**
     * Ajuster manuellement le gasoil d'un matériel
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajustement(Request $request)
    {
        // Valider les données d'entrée
        $validator = Validator::make($request->all(), [
            'materiel_id' => 'required|exists:materiels,id',
            'gasoil_avant' => 'required|numeric|min:0',
            'gasoil_apres' => 'required|numeric|min:0',
            'motif' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Récupérer le matériel
            $materiel = Materiel::find($request->materiel_id);

            if (!$materiel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Matériel non trouvé'
                ], 404);
            }

            // Vérifier que la valeur actuelle correspond à celle envoyée
            $actuelGasoil = (float) $materiel->actuelGasoil;
            $gasoilAvant = (float) $request->gasoil_avant;

            // On peut accepter une petite différence d'arrondi (0.01)
            if (abs($actuelGasoil - $gasoilAvant) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'La valeur actuelle du gasoil ne correspond pas à celle indiquée. Veuillez actualiser la page.',
                    'data' => [
                        'actuel' => $actuelGasoil,
                        'envoye' => $gasoilAvant
                    ]
                ], 409);
            }

            // Calculer la différence
            $gasoilApres = (float) $request->gasoil_apres;
            $difference = $gasoilApres - $actuelGasoil;

            // Mettre à jour le gasoil actuel du matériel
            $materiel->actuelGasoil = $gasoilApres;
            $materiel->save();

            // Créer l'opération de perte/ajustement
            $perteOperation = PerteGasoilOperation::create([
                'gasoil_avant' => $actuelGasoil,
                'gasoil_apres' => $gasoilApres,
                'gasoil_id' => null,
                'motif' => $request->motif,
                'user_id' => auth()->id(),
                'materiel_id' => $materiel->id
            ]);

            // Journaliser l'opération
            Log::info('Ajustement manuel du gasoil', [
                'materiel_id' => $materiel->id,
                'materiel_nom' => $materiel->nom_materiel,
                'gasoil_avant' => $actuelGasoil,
                'gasoil_apres' => $gasoilApres,
                'difference' => $difference,
                'motif' => $request->motif,
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name ?? auth()->user()->nom ?? 'System'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ajustement effectué avec succès',
                'data' => [
                    'materiel' => [
                        'id' => $materiel->id,
                        'nom' => $materiel->nom_materiel,
                        'gasoil_avant' => $actuelGasoil,
                        'gasoil_apres' => $gasoilApres,
                        'difference' => $difference
                    ],
                    'ajustement' => $perteOperation
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Erreur lors de l\'ajustement du gasoil', [
                'materiel_id' => $request->materiel_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajustement du gasoil',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
