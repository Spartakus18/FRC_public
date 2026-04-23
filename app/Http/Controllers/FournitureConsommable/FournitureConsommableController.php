<?php

namespace App\Http\Controllers\FournitureConsommable;

use App\Http\Controllers\Controller;
use App\Models\FournitureConsommable\FournitureConsommable;
use App\Models\Parametre\Unite;
use Illuminate\Http\Request;

class FournitureConsommableController extends Controller
{
    public function index()
    {
        $fournitures = FournitureConsommable::with('unite')->get();
        return response()->json($fournitures);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255|unique:fourniture_consommables',
            'unite_id' => 'required|exists:unites,id',
        ]);

        $fourniture = FournitureConsommable::create($request->all());
        return response()->json($fourniture->load('unite'), 201);
    }

    public function show($id)
    {
        $fourniture = FournitureConsommable::with('unite')->findOrFail($id);
        return response()->json($fourniture);
    }

    public function update(Request $request, $id)
    {
        $fourniture = FournitureConsommable::findOrFail($id);
        $request->validate([
            'nom' => 'required|string|max:255|unique:fourniture_consommables,nom,' . $fourniture->id,
            'unite_id' => 'required|exists:unites,id',
        ]);

        $fourniture->update($request->all());
        return response()->json($fourniture->load('unite'));
    }

    public function destroy($id)
    {
        $fourniture = FournitureConsommable::findOrFail($id);
        $fourniture->delete();
        return response()->json(['message' => 'Fourniture supprimée']);
    }

    public function create()
    {
        $unites = Unite::all();
        return response()->json(['unites' => $unites]);
    }

    public function edit($id)
    {
        $fourniture = FournitureConsommable::with('unite')->findOrFail($id);
        $unites = Unite::all();
        return response()->json([
            'fourniture' => $fourniture,
            'unites' => $unites,
        ]);
    }
}
