<?php

namespace App\Http\Controllers\AjustementStock;

use App\Http\Controllers\Controller;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\Parametre\CategorieArticle;
use App\Models\Parametre\Unite;
use App\Models\Produit\Categorie;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArticleDepotController extends Controller
{
    /**
     * Display a listing of the resource. test
     *
     * @return \Illuminate\Http\Response
     */
    // test
    public function index(Request $request)
    {
        $query = ArticleDepot::with(['uniteProduction', 'uniteLivraison', 'categorie']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('nom_article', 'like', '%' . $search . '%');
        }

        // Tri par nom (ordre alphabétique)
        $query->orderBy('nom_article', 'asc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $article = $query->paginate($perPage);

        return response()->json($article);
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
            'nom_article' => 'required|unique:article_depots,nom_article',
            'unite_production_id' => 'required|exists:unites,id',
            'unite_livraison_id' => 'required|exists:unites,id',
            'categorie_id' => 'required|exists:categorie_articles,id',
            'designation' => 'nullable'
        ]);

        $article = ArticleDepot::create($request->all());

        return $article;
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
        $article = ArticleDepot::find($id);
        return $article;
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
            'nom_article' => ['required', Rule::unique('article_depots')->ignore($id)],
            'unite_production_id' => 'required|exists:unites,id',
            'unite_livraison_id' => 'required|exists:unites,id',
            'categorie_id' => 'required|exists:categorie_articles,id',
            'designation' => 'nullable'
        ]);
        $article = ArticleDepot::find($id);
        $article->nom_article = $request->input('nom_article');
        $article->unite_production_id = $request->input('unite_production_id');
        $article->unite_livraison_id = $request->input('unite_livraison_id');
        $article->categorie_id = $request->input('categorie_id');
        $article->designation = $request->input('designation');
        $article->save();
        return response()->json([
            'message' => 'Article mis à jour avec succès !',
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
        $article = ArticleDepot::find($id);
        $article->delete();
        return response()->json([
            'message' => 'Article supprimer avec succès !',
        ]);
    }

    public function getUnites()
    {
        $unites = Unite::all();
        return $unites;
    }

    public function getCategories()
    {
        $categories = CategorieArticle::all();
        return $categories;
    }
}
