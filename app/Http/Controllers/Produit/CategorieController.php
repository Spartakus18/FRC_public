<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Models\Produit\Categorie;
use Illuminate\Http\Request;

class CategorieController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Categorie::query();

        // Filtre par recherche
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('nom_categorie', 'like', '%' . $search . '%');
        }

        // Tri par nom du conducteur (ordre alphabétique)
        $query->orderBy('nom_categorie', 'asc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $categorie = $query->paginate($perPage);

        return response()->json($categorie);
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
            'nom_categorie'=> 'required|string|unique:categories,nom_categorie',
        ]);

        $categorie = new Categorie();

        $categorie->nom_categorie = $request->input('nom_categorie');

        $categorie->save();

        return response()->json([
            'message'=>'Categorie ajouté avec succès !',
            'categorie'=>$categorie,
        ]);
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
        $categorie = Categorie::find($id);

        return $categorie;
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
            'nom_categorie'=> 'required|string|unique:categories,nom_categorie',
        ]);

        $categorie = Categorie::find($id);

        $categorie->nom_categorie = $request->input('nom_categorie');

        $categorie->save();

        return response()->json([
            'message'=>'Categorie mis à jour avec succès !',
            'categorie'=>$categorie,
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
        $categorie = Categorie::find($id);

        $categorie->delete();

        return response()->json([
            'message'=>'Catégorie supprimé avec succès !',
        ]);
    }
}
