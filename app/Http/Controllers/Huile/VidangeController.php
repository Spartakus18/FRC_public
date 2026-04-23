<?php

namespace App\Http\Controllers\Huile;

use App\Http\Controllers\Controller;
use App\Models\Gasoil\MaterielFusion;
use App\Models\Huile\ArticleVersement;
use App\Models\Huile\Subdivision;
use App\Models\Huile\Vidange;
use Illuminate\Http\Request;

class VidangeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $vidange = Vidange::with(['materiel', 'subdivision', 'article'])->get();
        return $vidange;
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
            'bon' => 'required | integer | unique:vidanges,bon',
            'materiel_id' => 'required | integer',
            'compteur' => 'required | integer',
            'subdivision_id' => 'required | integer',
            'article_id' => 'required | integer',
            'quantite' => 'required | integer',
            'heure_vidange' => 'required | integer',
            'compteur_vidange' => 'required | integer',
        ]);

        $vidange = new Vidange();

        $vidange->date = now();
        $vidange->bon = $request->input('bon');
        $vidange->materiel_id = $request->input('materiel_id');
        $vidange->compteur = $request->input('compteur');
        $vidange->subdivision_id = $request->input('subdivision_id');
        $vidange->article_id = $request->input('article_id');
        $vidange->quantite = $request->input('quantite');
        $vidange->heure_vidange = $request->input('heure_vidange');
        $vidange->compteur_vidange = $vidange->compteur + $vidange->heure_vidange;

        $vidange->save($request->all());
        return $vidange;
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
        $vidange = Vidange::find($id);
        return $vidange;
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
            'bon' => 'required | integer | unique:vidanges,bon',
            'materiel_id' => 'required | integer',
            'compteur' => 'required | integer',
            'subdivision_id' => 'required | integer',
            'article_id' => 'required | integer',
            'quantite' => 'required | integer',
            'heure_vidange' => 'required | integer',
            'compteur_vidange' => 'required | integer',
        ]);

        $vidange = Vidange::find($id);

        $vidange->date = now();
        $vidange->bon = $request->input('bon');
        $vidange->materiel_id = $request->input('materiel_id');
        $vidange->compteur = $request->input('compteur');
        $vidange->subdivision_id = $request->input('subdivision_id');
        $vidange->article_id = $request->input('article_id');
        $vidange->quantite = $request->input('quantite');
        $vidange->heure_vidange = $request->input('heure_vidange');
        $vidange->compteur_vidange = $vidange->compteur + $vidange->heure_vidange;

        $vidange->save();
        return response()->json([
            'message' => 'Vidange modifier avec succès !'
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
        $vidange = Vidange::find($id);
        $vidange->delete();
        return response()->json([
            'message' => 'Vidange supprimer avec succès !'
        ]);
    }
}
