<?php

namespace App\Http\Controllers\Parametre;

use App\Http\Controllers\Controller;
use App\Models\Parametre\Destination;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Destination::query();

        // Filtre par recherche
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('nom_destination', 'like', '%' . $search . '%');
        }

        // Tri par nom de destination (ordre alphabétique)
        $query->orderBy('nom_destination', 'asc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $destinations = $query->paginate($perPage);

        return response()->json($destinations);
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
            'nom_destination' => 'required|max:100|unique:destinations,nom_destination',
            'consommation_reference' => 'required|numeric',
        ]);

        $destination = new Destination();
        $destination->nom_destination = $request->input('nom_destination');
        $destination->consommation_reference = $request->input('consommation_reference');
        $destination->save();

        return $destination;
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
        $destination = Destination::find($id);

        return $destination;
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
            'nom_destination' => 'required|max:100|unique:destinations,nom_destination,'.$id,
            'consommation_reference' => 'required|numeric',
        ]);

        $destination = Destination::find($id);
        $destination->nom_destination = $request->input('nom_destination');
        $destination->consommation_reference = $request->input('consommation_reference');
        $destination->save();
        
        return response()->json([
            'message' => 'Destination mise à jour !'
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
        $destination = Destination::find($id);
        $destination->delete();
        return response()->json([
            'message' => 'Destination supprimée avec succès !',
        ]);
    }
}