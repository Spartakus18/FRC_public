<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Location\UniteFacturation;
use Illuminate\Http\Request;

class UniteFacturationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $unite = UniteFacturation::all();
        return $unite;
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
        $request->validate([
            'nom_unite'=>'required | string | unique:unite_facturations,nom_unite',
        ]);

        $unite = new UniteFacturation();

        $unite->nom_unite = $request->input('nom_unite');
        $unite->save($request->all());

        return $unite;
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
        $unite = UniteFacturation::find($id);
        return $unite;
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
            'nom_unite'=>'required | string | unique:unite_facturations,nom_unite',
        ]);

        $unite = UniteFacturation::find($id);

        $unite->nom_unite = $request->input('nom_unite');
        $unite->save();

        return response()->json([
            'message'=>'Unité mis à jour avec succès !'
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
        $unite = UniteFacturation::find($id);
        $unite->delete();
        return response()->json([
            'message'=>'Unité supprimer avec succès !',
        ]);
    }
}
