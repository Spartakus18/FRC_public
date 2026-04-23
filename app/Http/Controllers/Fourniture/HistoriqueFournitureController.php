<?php

namespace App\Http\Controllers\Fourniture;

use App\Http\Controllers\Controller;
use App\Models\Historique\HistoriqueFourniture;
use Illuminate\Http\Request;

class HistoriqueFournitureController extends Controller
{
    /**
     * Récupérer l'historique complet avec pagination
     */
    public function index(Request $request)
    {
        try {
            $query = HistoriqueFourniture::with(['fourniture', 'ancienMateriel', 'nouveauMateriel'])
                ->orderBy('date_action', 'desc')
                ->orderBy('created_at', 'desc');

            if ($request->has('fourniture_id') && $request->fourniture_id) {
                $query->where('fourniture_id', $request->fourniture_id);
            }

            if ($request->has('type_action') && $request->type_action) {
                $query->where('type_action', $request->type_action);
            }

            if ($request->has('date_start') && $request->date_start) {
                $query->whereDate('date_action', '>=', $request->date_start);
            }

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

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    /**
     * Récupérer l'historique d'une fourniture spécifique
     */
    public function show($fournitureId)
    {
        try {
            $historique = HistoriqueFourniture::where('fourniture_id', $fournitureId)
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
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
