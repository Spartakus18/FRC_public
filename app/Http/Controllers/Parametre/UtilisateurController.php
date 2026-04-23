<?php

namespace App\Http\Controllers\Parametre;

use App\Http\Controllers\Controller;
use App\Models\Parametre\Depot;
use App\Models\Parametre\Role_user;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UtilisateurController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = User::with(['role', 'depot'])->get();
        return $user;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $depot = Depot::all();
        $role = Role_user::all();

        return response()->json([
            'role' => $role,
            'depot' => $depot,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::with('role', 'depot')->find($id);
        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the authenticated user's account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateMyAccount(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non authentifié !',
            ], 401);
        }

        $request->validate([
            'nom' => 'required|string|max:100',
            'identifiant' => 'required|string|max:100|unique:users,identifiant,' . $user->id,
            'password' => 'nullable|min:6|confirmed',
        ]);

        try {
            $user->nom = $request->input('nom');
            $user->identifiant = $request->input('identifiant');

            // Ne mettre à jour le mot de passe que s'il est fourni
            if ($request->filled('password')) {
                $user->password = Hash::make($request->input('password'));
            }

            $user->save();

            return response()->json([
                'message' => 'Profil mis à jour avec succès !',
                'user' => $user->load('role', 'depot')
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Une erreur est survenue lors de la mise à jour !',
                'error' => $th->getMessage()
            ], 500);
        }
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
            'nom' => 'required|string|max:100',
            'identifiant' => 'string|max:100|unique:users,identifiant,' . $id,
            'password' => 'sometimes|min:6',
        ]);
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'Utilisateur introuvable !',
                ]);
            } else {
                $user->nom = $request->input('nom');
                $user->role_id = $request->input('role_id');
                $user->identifiant = $request->input('identifiant');
                $user->depot_id = $request->input('depot_id');
                $user->password = Hash::make($request->input('password'));

                $user->save($request->all());

                return response()->json([
                    'message' => 'Personne modifier avec succès !'
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
        $user = User::find($id);
        $user->delete();
        return response()->json([
            'message' => 'Utilisateur supprimer avec succès !'
        ]);
    }
}
