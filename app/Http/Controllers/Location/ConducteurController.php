<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Location\Conducteur;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConducteurController extends Controller
{
    /**
     * Display a listing of the resource with filters and pagination.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Conducteur::query();

        // Filtre par recherche (nom du conducteur)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('nom_conducteur', 'like', '%' . $search . '%');
        }

        // Tri par nom du conducteur (ordre alphabétique)
        $query->orderBy('nom_conducteur', 'asc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $conducteurs = $query->paginate($perPage);

        return response()->json($conducteurs);
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
            'nom_conducteur' => 'required|string|unique:conducteurs,nom_conducteur',
        ]);

        $conducteur = Conducteur::create([
            'nom_conducteur' => $request->input('nom_conducteur'),
        ]);

        return response()->json([
            'message' => 'Conducteur ajouté avec succès',
            'data' => $conducteur
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $conducteur = Conducteur::find($id);

        if (!$conducteur) {
            return response()->json([
                'message' => 'Conducteur non trouvé'
            ], 404);
        }

        return response()->json($conducteur);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $conducteur = Conducteur::find($id);

        if (!$conducteur) {
            return response()->json([
                'message' => 'Conducteur non trouvé'
            ], 404);
        }

        return response()->json($conducteur);
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
            'nom_conducteur' => [
                'required',
                'string',
                Rule::unique('conducteurs', 'nom_conducteur')->ignore($id)
            ],
        ]);

        $conducteur = Conducteur::find($id);

        if (!$conducteur) {
            return response()->json([
                'message' => 'Conducteur non trouvé'
            ], 404);
        }

        $conducteur->update([
            'nom_conducteur' => $request->input('nom_conducteur'),
        ]);

        return response()->json([
            'message' => 'Conducteur mis à jour avec succès !',
            'data' => $conducteur
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
        $conducteur = Conducteur::find($id);

        if (!$conducteur) {
            return response()->json([
                'message' => 'Conducteur non trouvé'
            ], 404);
        }

        $conducteur->delete();

        return response()->json([
            'message' => 'Conducteur supprimé avec succès !',
        ]);
    }

    /**
     * Get all conductors for dropdown (simple list for forms)
     */
    public function getAllForDropdown()
    {
        $conducteurs = Conducteur::orderBy('nom_conducteur', 'asc')->get(['id', 'nom_conducteur']);
        return response()->json($conducteurs);
    }
}
