<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Location\AideChauffeur;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AideChauffeurController extends Controller
{
    /**
     * Display a listing of the resource with filters and pagination.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = AideChauffeur::query();

        // Filtre par recherche (nom de l'aide chauffeur)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('nom_aideChauffeur', 'like', '%' . $search . '%');
        }

        // Tri par nom (ordre alphabétique)
        $query->orderBy('nom_aideChauffeur', 'asc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $aides = $query->paginate($perPage);

        return response()->json($aides);
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
            'nom_aideChauffeur' => 'required|string|unique:aide_chauffeurs,nom_aideChauffeur',
        ]);

        $aide = AideChauffeur::create([
            'nom_aideChauffeur' => $request->input('nom_aideChauffeur'),
        ]);

        return response()->json([
            'message' => 'Aide chauffeur ajouté avec succès !',
            'data' => $aide
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
        $aide = AideChauffeur::find($id);

        if (!$aide) {
            return response()->json([
                'message' => 'Aide chauffeur non trouvé'
            ], 404);
        }

        return response()->json($aide);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $aide = AideChauffeur::find($id);

        if (!$aide) {
            return response()->json([
                'message' => 'Aide chauffeur non trouvé'
            ], 404);
        }

        return response()->json($aide);
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
            'nom_aideChauffeur' => [
                'required',
                'string',
                Rule::unique('aide_chauffeurs', 'nom_aideChauffeur')->ignore($id)
            ],
        ]);

        $aide = AideChauffeur::find($id);

        if (!$aide) {
            return response()->json([
                'message' => 'Aide chauffeur non trouvé'
            ], 404);
        }

        $aide->update([
            'nom_aideChauffeur' => $request->input('nom_aideChauffeur'),
        ]);

        return response()->json([
            'message' => 'Aide chauffeur mis à jour avec succès !',
            'data' => $aide
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
        $aide = AideChauffeur::find($id);

        if (!$aide) {
            return response()->json([
                'message' => 'Aide chauffeur non trouvé'
            ], 404);
        }

        $aide->delete();

        return response()->json([
            'message' => 'Aide chauffeur supprimé avec succès !',
        ]);
    }

    /**
     * Get all aide chauffeurs for dropdown (simple list for forms)
     */
    public function getAllForDropdown()
    {
        $aides = AideChauffeur::orderBy('nom_aideChauffeur', 'asc')->get(['id', 'nom_aideChauffeur']);
        return response()->json($aides);
    }
}
