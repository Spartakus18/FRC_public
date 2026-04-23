<?php

namespace App\Http\Controllers\Parametre;

use App\Http\Controllers\Controller;
use App\Models\Parametre\Depot;
use Illuminate\Http\Request;

class DepotController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Depot::query();

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('nom', 'like', '%' . $search . '%');
        }

        // Tri par nom (ordre alphabétique)
        $query->orderBy('nom', 'asc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $depot = $query->paginate($perPage);

        return response()->json($depot);
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
            'nom_depot' => 'required | max:100 | unique:depots,nom_depot',
        ]);

        $depot = new Depot();
        $depot->nom_depot = $request->input('nom_depot');
        $depot->save($request->all());
        return $depot;
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
        $depot = Depot::find($id);
        return $depot;
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
            'nom_depot' => 'required | max:100 | unique:depots,nom_depot',
        ]);

        $depot = Depot::find($id);
        $depot->nom_depot = $request->input('nom_depot');
        $depot->save();

        return response()->json([
            'message' => 'Dépot mis à jour !'
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
        $depot = Depot::find($id);
        $depot->delete();
        return response()->json([
            'message' => 'Dépot supprimé avec succès !',
        ]);
    }
}
