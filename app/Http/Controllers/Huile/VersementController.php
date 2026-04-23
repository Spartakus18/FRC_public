<?php

namespace App\Http\Controllers\Huile;

use App\Http\Controllers\Controller;
use App\Models\Gasoil\MaterielFusion;
use App\Models\Huile\ArticleVersement;
use App\Models\Huile\Subdivision;
use App\Models\Huile\Versement;
use Illuminate\Http\Request;

class VersementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $versement = Versement::with(['materiel', 'subdivision', 'article'])->get();
        return $versement;
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
        $article = ArticleVersement::all();

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
            'bon' => 'required | integer',
            'materiel_id' => 'required | integer',
            'subdivision_id' => 'required | integer',
            'article_id' => 'required | integer',
            'quantite' => 'required | integer',
        ]);

        $versement = new Versement();

        $versement->date = now();
        $versement->date = $request->input('bon');
        $versement->materiel_id = $request->input('materiel_id');
        $versement->subdivision_id = $request->input('subdivision_id');
        $versement->article_id = $request->input('article_id');
        $versement->quantite = $request->input('quantite');

        $versement->save($request->all());
        return $versement;
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
        $versement = Versement::find($id);
        return $versement;
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
            'bon' => 'required | integer',
            'materiel_id' => 'required | integer',
            'subdivision_id' => 'required | integer',
            'article_id' => 'required | integer',
            'quantite' => 'required | integer',
        ]);

        $versement = Versement::find($id);

        $versement->date = now();
        $versement->date = $request->input('bon');
        $versement->materiel_id = $request->input('materiel_id');
        $versement->subdivision_id = $request->input('subdivision_id');
        $versement->article_id = $request->input('article_id');
        $versement->quantite = $request->input('quantite');

        $versement->save($request->all());
        return response()->json([
            'message' => 'Versement mis à jour avec succès !',
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
        $versement = Versement::find($id);
        $versement->delete();
        return response()->json([
            'message' => 'Versement supprimer avec succès !',
        ]);
    }
}
