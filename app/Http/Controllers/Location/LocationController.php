<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\Location\Conducteur;
use App\Models\Location\Location;
use App\Models\Location\UniteFacturation;
use App\Models\Parametre\Client;
use App\Models\Parametre\Materiel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::with(['client', 'materiel', 'conducteur', 'unite', 'produit'])->get();
        return response()->json($locations);
    }

    public function create()
    {
        $clients = Client::all();
        $materiels = Materiel::all();
        $conducteurs = Conducteur::all();
        $unites = UniteFacturation::all();
        $produit = ArticleDepot::all();
        return response()->json([
            'clients' => $clients,
            'materiels' => $materiels,
            'conducteurs' => $conducteurs,
            'unites' => $unites,
            'produit' => $produit,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'client_id' => 'required|integer|exists:clients,id',
            'observation' => 'nullable|string',
            'locations' => 'required|array|min:1',
            'locations.*.materiel_id' => 'required|integer|exists:materiel_locations,id',
            'locations.*.gasoil_quantite' => 'required|numeric',
            'locations.*.gasoil_avant' => 'required|numeric',
            'locations.*.jauge_debut' => 'required|numeric',
            'locations.*.jauge_fin' => 'required|numeric',
            'locations.*.heures_debut' => 'required',
            'locations.*.heures_fin' => 'required',
            'locations.*.compteur_debut' => 'required|numeric',
            'locations.*.compteur_fin' => 'required|numeric',
            'locations.*.conducteur_id' => 'required|integer|exists:conducteurs,id',
            'locations.*.facturation_unite_Id' => 'required|integer|exists:unite_facturations,id',
            'locations.*.facturation_quantite' => 'required|numeric|min:0',
            'locations.*.facturation_prixU' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $locationsData = $request->input('locations', []);
            $createdLocations = [];

            foreach ($locationsData as $locationItem) {
                $location = new Location();

                $location->date = $request->input('date');
                $location->client_id = $request->input('client_id');
                $location->observation = $request->input('observation');
                $location->materiel_id = $locationItem['materiel_id'];
                $location->gasoil_quantite = $locationItem['gasoil_quantite'];
                $location->gasoil_avant = $locationItem['gasoil_avant'];
                $location->jauge_debut = $locationItem['jauge_debut'];
                $location->jauge_fin = $locationItem['jauge_fin'];
                $location->heures_debut = $locationItem['heures_debut'];
                $location->heures_fin = $locationItem['heures_fin'];
                $location->compteur_debut = $locationItem['compteur_debut'];
                $location->compteur_fin = $locationItem['compteur_fin'];
                $location->conducteur_id = $locationItem['conducteur_id'];
                $location->facturation_unite_Id = $locationItem['facturation_unite_Id'];
                $location->facturation_quantite = $locationItem['facturation_quantite'];
                $location->facturation_prixU = $locationItem['facturation_prixU'];
                $location->facturation_prixT = $locationItem['facturation_quantite'] * $locationItem['facturation_prixU'];

                $location->save();
                $createdLocations[] = $location->load(['client', 'materiel', 'conducteur', 'unite', 'produit']);
            }

            DB::commit();
            return response()->json([
                'message' => 'Locations créées avec succès !',
                'locations' => $createdLocations
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création des locations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $location = Location::with(['client', 'materiel', 'conducteur', 'unite'])->findOrFail($id);
        return response()->json($location);
    }

    public function edit($id)
    {
        $location = Location::with(['client', 'materiel', 'conducteur', 'unite'])->findOrFail($id);
        return response()->json($location);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'date' => 'required|date',
            'client_id' => 'required|integer|exists:clients,id',
            'observation' => 'nullable|string',
            'materiel_id' => 'required|integer|exists:materiel_locations,id',
            'gasoil_quantite' => 'required|numeric',
            'gasoil_avant' => 'required|numeric',
            'jauge_debut' => 'required|numeric',
            'jauge_fin' => 'required|numeric',
            'heures_debut' => 'required',
            'heures_fin' => 'required',
            'compteur_debut' => 'required|numeric',
            'compteur_fin' => 'required|numeric',
            'conducteur_id' => 'required|integer|exists:conducteurs,id',
            'facturation_unite_Id' => 'required|integer|exists:unite_facturations,id',
            'facturation_quantite' => 'required|numeric|min:0',
            'facturation_prixU' => 'required|numeric|min:0',
        ]);

        try {
            $location = Location::findOrFail($id);
            $location->date = $request->input('date');
            $location->client_id = $request->input('client_id');
            $location->observation = $request->input('observation');
            $location->materiel_id = $request->input('materiel_id');
            $location->gasoil_quantite = $request->input('gasoil_quantite');
            $location->gasoil_avant = $request->input('gasoil_avant');
            $location->jauge_debut = $request->input('jauge_debut');
            $location->jauge_fin = $request->input('jauge_fin');
            $location->heures_debut = $request->input('heures_debut');
            $location->heures_fin = $request->input('heures_fin');
            $location->compteur_debut = $request->input('compteur_debut');
            $location->compteur_fin = $request->input('compteur_fin');
            $location->conducteur_id = $request->input('conducteur_id');
            $location->facturation_unite_Id = $request->input('facturation_unite_Id');
            $location->facturation_quantite = $request->input('facturation_quantite');
            $location->facturation_prixU = $request->input('facturation_prixU');
            $location->facturation_prixT = $request->input('facturation_quantite') * $request->input('facturation_prixU');

            $location->save();
            return response()->json([
                'message' => 'Location modifiée avec succès !',
                'location' => $location->load(['client', 'materiel', 'conducteur', 'unite'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la modification de la location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $location = Location::findOrFail($id);
            $location->delete();
            return response()->json([
                'message' => 'Location supprimée avec succès !',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:locations,id'
        ]);
        try {
            Location::whereIn('id', $request->ids)->delete();
            return response()->json([
                'message' => 'Locations supprimées avec succès !',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression des locations',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
