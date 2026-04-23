<?php

namespace App\Http\Controllers;

use App\Models\Parametre\Depot;
use App\Models\Parametre\Role_user;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $role = Role_user::get();
        $depot = Depot::get();
        return response()->json([
            'role'=>$role,
            'depot'=>$depot,
        ]);
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
            'nom' => 'required | string | max:100',
            'identifiant' => 'string | max:100 | unique:users,identifiant',
            'password' => 'required | min:6',
        ]);

        // Création de l'utilisateur
        $user = new User();

        $user->nom = $request->input('nom');
        $user->role_id = $request->input('role_id');
        $user->identifiant = $request->input('identifiant');
        $user->depot_id = $request->input('depot_id');
        $user->password = Hash::make($request->input('password'));

        $user->save($request->all());
        return $user;
    }

    public function login(Request $request) {
        $credentials = $request->validate([
            'identifiant' => 'required | string',
            'password' =>'required',
        ]);

        if (Auth::attempt($credentials)) {
            /** @var \App\Models\Parametre\User **/
            $user = Auth::user();
            $token = $user->createToken('react-tax-token')->plainTextToken;
            return response()->json(['token' => $token, 'user' => $user]);
        }
        return response()->json(['error' => 'Identifiants invalides'], 401);
    }

    public function logout(Request $request) {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Déconnecté avec succès']);
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
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
