<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Historique\HistoriquePneu;
use Illuminate\Http\Request;

class HistoriquePneuController extends Controller
{
    /**
     * Récupérer l'historique d'un pneu spécifique
     */
    public function show($pneuId)
    {
        try {
            $historique = HistoriquePneu::where('pneu_id', $pneuId)
                ->orderBy('date_action', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($historique);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération de l\'historique',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer l'historique complet avec pagination
     */
    public function index(Request $request)
    {
        try {
            $query = HistoriquePneu::with(['pneu', 'ancienMateriel', 'nouveauMateriel'])
                ->orderBy('date_action', 'desc')
                ->orderBy('created_at', 'desc');

            // Filtre par pneu_id si fourni
            if ($request->has('pneu_id') && $request->pneu_id) {
                $query->where('pneu_id', $request->pneu_id);
            }

            // Filtre par type d'action
            if ($request->has('type_action') && $request->type_action) {
                $query->where('type_action', $request->type_action);
            }

            // Filtre par date de début
            if ($request->has('date_start') && $request->date_start) {
                $query->whereDate('date_action', '>=', $request->date_start);
            }

            // Filtre par date de fin
            if ($request->has('date_end') && $request->date_end) {
                $query->whereDate('date_action', '<=', $request->date_end);
            }

            $historique = $query->paginate(20);

            return response()->json($historique);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération de l\'historique',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
