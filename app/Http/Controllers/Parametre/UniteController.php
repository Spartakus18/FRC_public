<?php

namespace App\Http\Controllers\Parametre;

use App\Http\Controllers\Controller;
use App\Models\Parametre\Unite;
use Illuminate\Http\Request;

class UniteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Unite::query();

        // Filtre par recherche
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('nom_unite', 'like', '%' . $search . '%');
        }

        // Tri par nom du conducteur (ordre alphabétique)
        $query->orderBy('nom_unite', 'asc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $unite = $query->paginate($perPage);

        return response()->json($unite);
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
            'nom_unite'=> 'required | max:100 | unique:unites,nom_unite',
        ]);

        $unite = new Unite();
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
        $unite = Unite::find($id);

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
            'nom_unite'=>'required|max:100|unique:unites,nom_unite,'.$id,
        ]);

        $unite = Unite::find($id);
        $unite->nom_unite = $request->input('nom_unite');
        $unite->save();
        return response()->json([
            'message'=>'Unité mise à jour !'
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
        $unite = Unite::find($id);
        $unite->delete();
        return response()->json([
            'message'=>'Unité supprimé avec succès !',
        ]);
    }
}
