<?php

namespace App\Http\Controllers\Materiel;

use App\Http\Controllers\Controller;
use App\Models\Huile\Subdivision;
use Illuminate\Http\Request;

class SubdivisionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $subdivision = Subdivision::all();
        return $subdivision;
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
            'nom_subdivision' => 'required | string | unique:subdivisions,nom_subdivision',
        ], [
            'nom_subdivision.required' => 'Le nom de la subdivision est obligatoire.',
            'nom_subdivision.string'   => 'Le nom de la subdivision doit être une chaîne de caractères.',
            'nom_subdivision.unique'   => 'Cette subdivision existe déjà.',
        ]);

        $subdivision = new Subdivision();

        $subdivision->nom_subdivision = $request->input('nom_subdivision');
        $subdivision->save();
        return $subdivision;
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
        $subdivision = Subdivision::find($id);
        return $subdivision;
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
            'nom_subdivision' => 'required | string | unique:subdivisions,nom_subdivision',
        ]);

        $subdivision = Subdivision::find($id);

        $subdivision->nom_subdivision = $request->input('nom_subdivision');
        $subdivision->save();
        return response()->json([
            'message' => 'Subdivision modifier avec succès !',
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
        $subdivision = Subdivision::find($id);
        $subdivision->delete();
        return response()->json([
            'message' => 'Subdivision supprimer avec succès !',
        ]);
    }
}
