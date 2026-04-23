<?php

namespace App\Http\Controllers\Parametre;

use App\Http\Controllers\Controller;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\Parametre\Article;
use App\Models\Parametre\Unite;
use App\Models\Produit\Categorie;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $article = Article::with(['unite', 'categorieArticle'])->get();
        return $article;
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
            'designation' => 'required | max:100 | unique:articles,designation',
        ]);

        $article = new Article();
        $article->categorie_id = $request->input('categorie_id');
        $article->designation = $request->input('designation');
        $article->unite_id = $request->input('unite_id');
        $article->save($request->all());

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
        $article = Article::with(['unite', 'categorieArticle'])->find($id);
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
            'designation' => 'required | max:100 | unique:articles,designation',
        ]);
        try {
            $article = Article::find($id);

            if (!$article) {
                return response()->json([
                    'message' => 'Article introuvable !'
                ]);
            } else {
                $article->categorie_id = $request->input('categorie_id');
                $article->designation = $request->input('designation');
                $article->unite_id = $request->input('unite_id');
                $article->save($request->all());

                return response()->json([
                    'message' => 'Article mis à jour !',
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Une erreur est survenue !'
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $article = Article::find($id);
        $article->delete();
        return response()->json([
            'message' => 'Article supprimer avec succès !',
        ]);
    }

    public function getHuileVersement()
    {
        $articleVersemment = ArticleDepot::where('categorie_id', 2)->get();
        return response()->json($articleVersemment);
    }
}
