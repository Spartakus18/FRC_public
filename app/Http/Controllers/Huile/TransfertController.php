<?php

namespace App\Http\Controllers\Huile;

use App\Http\Controllers\Controller;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\Gasoil\MaterielFusion;
use App\Models\Huile\ArticleVersement;
use App\Models\Huile\Subdivision;
use App\Models\Huile\Transfert;
use Illuminate\Http\Request;

class TransfertController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $transfert = Transfert::with(['materiel1', 'materiel2', 'subdivision1', 'subdivision2', 'article'])->get();
        return $transfert;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $materiel = MaterielFusion::all();
        $subdivision = Subdivision::all();
        $article = ArticleDepot::all();

        return response()->json([
            'materiel' => $materiel,
            'subdivision' => $subdivision,
            'article' => $article,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required | date',
            'bon' => 'required | integer | unique:transfert_huiles,bon',
            'transfertDe' => 'required | integer',
            'subdivision1' => 'required | integer',
            'transfererA' => 'required | integer',
            'subdivision2' => 'required | integer',
            'article_id' => 'required | integer',
            'quantite' => 'required | integer',
        ]);

        $transfert = new Transfert();

        $transfert->date = $request->input('date');
        $transfert->bon = $request->input('bon');
        $transfert->transfertDe = $request->input('transfertDe');
        $transfert->subdivision1 = $request->input('subdivision1');
        $transfert->transfererA = $request->input('transfererA');
        $transfert->subdivision2 = $request->input('subdivision2');
        $transfert->article_id = $request->input('article_id');
        $transfert->quantite = $request->input('quantite');

        $transfert->save();
        return $transfert;
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
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $transfert = Transfert::find($id);
        return $transfert;
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
        $request->validate([
            'date' => 'required | date',
            'bon' => 'required | integer | unique:transfert_huiles,bon',
            'transfertDe' => 'required | integer',
            'subdivision1' => 'required | integer',
            'transfererA' => 'required | integer',
            'subdivision2' => 'required | integer',
            'article_id' => 'required | integer',
            'quantite' => 'required | integer',
        ]);

        $transfert = Transfert::find($id);

        $transfert->date = $request->input('date');
        $transfert->bon = $request->input('bon');
        $transfert->transfertDe = $request->input('transfertDe');
        $transfert->subdivision1 = $request->input('subdivision1');
        $transfert->transfererA = $request->input('transfererA');
        $transfert->subdivision2 = $request->input('subdivision2');
        $transfert->article_id = $request->input('article_id');
        $transfert->quantite = $request->input('quantite');

        $transfert->save();
        return response()->json([
            'message' => 'Transfert modifier avec succès !',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $transfert = Transfert::find($id);
        $transfert->delete();
        return response()->json([
            'message' => 'Transfert supprimer avec succès !',
        ]);
    }
}
