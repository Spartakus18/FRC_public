<?php

namespace App\Http\Controllers\Parametre;

use App\Http\Controllers\Controller;
use App\Models\Parametre\SubventionMateriel;
use Illuminate\Http\Request;

class SubventionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = SubventionMateriel::query();

        // Filtre par recherche
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('nom', 'like', '%' . $search . '%');
        }

        // Tri par nom (ordre alphabétique)
        $query->orderBy('nom', 'asc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $subvention = $query->paginate($perPage);

        return response()->json($subvention);
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
            'nom_subvention'=>'required | max:100 | unique:subvention_materiels,nom_subvention',
        ]);

        $subvention = new SubventionMateriel();

        $subvention->nom_subvention = $request->input('nom_subvention');
        $subvention->save($request->all());

        return $subvention;
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
        $subvention = SubventionMateriel::find($id);
        return $subvention;
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
        $subvention = SubventionMateriel::find($id);

        $subvention->nom_subvention = $request->input('nom_subvention');
        $subvention->save();

        return response()->json([
            'message'=>'Subvention mis à jour avec succès !',
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
        $subvention = SubventionMateriel::find($id);
        $subvention->delete();
        return response()->json([
            'message'=>'Subvention supprimer avec succès !',
        ]);
    }
}
