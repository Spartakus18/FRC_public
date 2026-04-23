<?php

namespace App\Http\Controllers\AjustementStock;

use App\Http\Controllers\Controller;
use App\Models\AjustementStock\Lieu_stockage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LieuStockageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $lieu = Lieu_stockage::all();
        return $lieu;
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|unique:lieu_stockages,nom',
            'heure_chauffeur' => 'nullable'
        ], [
            'nom.required' => 'Le nom du lieu de stockage est obligatoire.',
            'nom.unique'   => 'Ce nom de lieu de stockage est déjà utilisé.',
        ]);

        $lieu = new Lieu_stockage();

        $lieu->nom = $request->input('nom');
        $lieu->heure_chauffeur = $request->input('heure_chauffeur');
        $lieu->save();

        return response()->json([
            'message' => "Nouvelle lieu de stockage enregistré !",
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     */
    public function edit($id)
    {
        $lieu = Lieu_stockage::find($id);
        return $lieu;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nom' => [
                'required',
                Rule::unique('lieu_stockages', 'nom')->ignore($id),
            ],
            'heure_chauffeur' => 'nullable'
        ], [
            'nom.required' => 'Le nom du lieu de stockage est obligatoire.',
            'nom.unique'   => 'Ce nom de lieu de stockage est déjà utilisé.',
        ]);

        $lieu = Lieu_stockage::find($id);

        $lieu->nom = $request->input('nom');
        $lieu->heure_chauffeur = $request->input('heure_chauffeur');
        $lieu->save();

        return response()->json([
            'message' => "Lieu de stockage mis à jour !",
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $lieu = Lieu_stockage::find($id);

        if (!$lieu) {
            return response()->json([
                'message' => "Lieu de stockage introuvable !",
            ], 404);
        }

        DB::transaction(function () use ($id, $lieu) {
            // Colonnes FK nullable: on détache le lieu avant suppression.
            DB::table('bon_gasoils')
                ->where('source_lieu_stockage_id', $id)
                ->update(['source_lieu_stockage_id' => null]);

            DB::table('bon_huiles')
                ->where('source_lieu_stockage_id', $id)
                ->update(['source_lieu_stockage_id' => null]);

            DB::table('gasoils')
                ->where('source_lieu_stockage_id', $id)
                ->update(['source_lieu_stockage_id' => null]);

            DB::table('huiles')
                ->where('source_lieu_stockage_id', $id)
                ->update(['source_lieu_stockage_id' => null]);

            DB::table('fournitures')
                ->where('lieu_stockage_id', $id)
                ->update(['lieu_stockage_id' => null]);

            // Tables avec FK non-nullables sans cascade: suppression des enregistrements liés.
            $bonTransfertIds = DB::table('bon_transferts')
                ->where('lieu_stockage_depart_id', $id)
                ->orWhere('lieu_stockage_arrive_id', $id)
                ->pluck('id');

            $transfertIds = DB::table('transfert_produits')
                ->where(function ($query) use ($id, $bonTransfertIds) {
                    $query->where('lieu_stockage_depart_id', $id)
                        ->orWhere('lieu_stockage_arrive_id', $id);

                    if ($bonTransfertIds->isNotEmpty()) {
                        $query->orWhereIn('bon_transfert_id', $bonTransfertIds);
                    }
                })
                ->pluck('id');

            if ($transfertIds->isNotEmpty()) {
                DB::table('consommation_gasoils')
                    ->whereIn('transfert_produit_id', $transfertIds)
                    ->delete();

                DB::table('transfert_produits')
                    ->whereIn('id', $transfertIds)
                    ->delete();
            }

            DB::table('bon_transferts')
                ->where('lieu_stockage_depart_id', $id)
                ->orWhere('lieu_stockage_arrive_id', $id)
                ->delete();

            DB::table('melange_produits')
                ->where('lieu_stockage_a_id', $id)
                ->orWhere('lieu_stockage_b_id', $id)
                ->orWhere('lieu_stockage_final_id', $id)
                ->delete();

            $lieu->delete();
        });

        return response()->json([
            'message' => "Lieu de stockage supprimé !",
        ]);
    }
}
