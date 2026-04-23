<?php

namespace App\Http\Controllers\Parametre;

use App\Http\Controllers\Controller;
use App\Models\Parametre\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Client::query();

        // Filtre par recherche (nom du client)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('nom_client', 'like', '%' . $search . '%');
        }

        // Tri par nom du conducteur (ordre alphabétique)
        $query->orderBy('nom_client', 'asc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $client = $query->paginate($perPage);

        return response()->json($client);
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
            'nom_client' => 'required|max:100|unique:clients,nom_client',
        ]);

        $client = new Client();

        $client->nom_client = $request->input('nom_client');
        $client->save();

        return $client;
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
        $client = Client::find($id);
        return $client;
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
            'nom_client' => 'required|max:100|unique:clients,nom_client,' . $id,
        ]);

        try {
            $client = Client::findOrFail($id);

            if (!$client) {
                return response()->json([
                    'message' => 'Client introuvable',
                ]);
            } else {
                $client->nom_client = $request->input('nom_client');
                $client->save($request->all());

                return $client;
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
        $client = Client::find($id);
        $client->delete();
        return response()->json([
            'message' => 'Client supprimer avec succès !'
        ]);
    }
}
