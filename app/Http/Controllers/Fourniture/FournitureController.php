<?php

namespace App\Http\Controllers\Fourniture;

use App\Exports\FournitureExport;
use App\Http\Controllers\Controller;
use App\Models\Fourniture\Fourniture;
use App\Models\Historique\HistoriqueFourniture;
use App\Models\Parametre\Materiel;
use App\Models\AjustementStock\Lieu_stockage;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class FournitureController extends Controller
{
    /**
     * Liste des fournitures avec filtres.
     */
    public function index(Request $request)
    {
        $query = Fourniture::with(['materiel', 'lieuStockage']);

        // Recherche
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom_article', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('numero_serie', 'like', "%{$search}%")
                    ->orWhere('autre_materiel_nom', 'like', "%{$search}%")
                    ->orWhereHas('materiel', fn($q) => $q->where('nom_materiel', 'like', "%{$search}%"));
            });
        }

        // Filtre par état
        if ($request->filled('etat')) {
            $query->where('etat', $request->etat);
        }

        // Filtre par disponibilité
        if ($request->has('is_dispo') && $request->is_dispo !== null) {
            $query->where('is_dispo', $request->is_dispo);
        }

        // Filtre par lieu de stockage
        if ($request->filled('lieu_stockage_id')) {
            $query->where('lieu_stockage_id', $request->lieu_stockage_id);
        }

        // Tri
        $query->orderBy('created_at', 'desc');

        return response()->json($query->get());
    }

    /**
     * Données pour les formulaires (création / édition).
     */
    public function create()
    {
        return response()->json([
            'materiels'       => Materiel::all(),
            'lieux_stockage' => Lieu_stockage::all(),
        ]);
    }

    /**
     * Export Excel des fournitures filtrées.
     */
    public function exportExcel(Request $request)
    {
        return Excel::download(new FournitureExport($request), 'fournitures.xlsx');
    }

    /**
     * Créer une nouvelle fourniture.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom_article'          => 'required|string|max:255',
            'reference'            => 'required|string|max:100',
            'numero_serie'         => 'nullable|string|max:100|unique:fournitures',
            'etat'                 => 'required|in:neuf,bon,moyen,a_verifier,hors_service',
            'date_acquisition'     => 'required|date',
            'commentaire'          => 'nullable|string',
            'materiel_id_associe'  => 'nullable|exists:materiels,id',
            'autre_materiel_nom'   => 'nullable|string|max:255',
            'lieu_stockage_id'     => 'nullable|exists:lieu_stockages,id',
            'localisation_actuelle' => 'nullable|string|in:chantier,maintenance,atelier_maintenance',
        ]);

        // Déterminer si la fourniture est disponible (aucune association)
        $isDispo = empty($validated['materiel_id_associe']) && empty($validated['autre_materiel_nom']);

        $fournitureData = [
            'nom_article'         => $validated['nom_article'],
            'reference'           => $validated['reference'],
            'numero_serie'        => $validated['numero_serie'] ?? null,
            'etat'                => $validated['etat'],
            'is_dispo'           => $isDispo,
            'date_acquisition'   => $validated['date_acquisition'],
            'materiel_id_associe' => $validated['materiel_id_associe'] ?? null,
            'autre_materiel_nom' => $validated['autre_materiel_nom'] ?? null,
            'commentaire'        => $validated['commentaire'] ?? null,
        ];

        if ($isDispo) {
            // --- EN STOCK ---
            $fournitureData['lieu_stockage_id']      = $validated['lieu_stockage_id'] ?? null;
            $fournitureData['localisation_actuelle'] = null;
            $fournitureData['date_sortie_stock']     = null;
            $fournitureData['date_retour_stock']     = null;
        } else {
            // --- ASSOCIÉE (sortie du stock) ---
            $fournitureData['lieu_stockage_id']      = null;
            $fournitureData['localisation_actuelle'] = $validated['localisation_actuelle'] ?? 'chantier';
            $fournitureData['date_sortie_stock']     = now();
            $fournitureData['date_retour_stock']     = null;
        }

        $fourniture = Fourniture::create($fournitureData);

        // Enregistrement dans l'historique
        HistoriqueFourniture::create([
            'fourniture_id'        => $fourniture->id,
            'type_action'         => 'creation',
            'date_action'         => now(),
            'ancien_materiel_id'  => null,
            'ancien_materiel_nom' => null,
            'nouveau_materiel_id' => $fourniture->materiel_id_associe,
            'nouveau_materiel_nom' => $fourniture->materielAssocieNom,
            'commentaire'         => 'Création de la fourniture',
            'etat'               => $fourniture->etat,
        ]);

        return response()->json([
            'message' => 'Fourniture ajoutée avec succès',
            'data'    => $fourniture->load(['materiel', 'lieuStockage']),
        ], 201);
    }

    /**
     * Afficher une fourniture spécifique.
     */
    public function show($id)
    {
        $fourniture = Fourniture::with(['materiel', 'lieuStockage'])->find($id);

        if (!$fourniture) {
            return response()->json(['message' => 'Fourniture introuvable'], 404);
        }

        return response()->json($fourniture);
    }

    /**
     * Mettre à jour une fourniture.
     */
    public function update(Request $request, $id)
    {
        $fourniture = Fourniture::find($id);

        if (!$fourniture) {
            return response()->json(['message' => 'Fourniture introuvable'], 404);
        }

        $validated = $request->validate([
            'nom_article'          => 'required|string|max:255',
            'reference'            => 'required|string|max:100',
            'numero_serie'         => 'nullable|string|max:100|unique:fournitures,numero_serie,' . $id,
            'etat'                 => 'required|in:neuf,bon,moyen,a_verifier,hors_service',
            'date_acquisition'     => 'required|date',
            'materiel_id_associe'  => 'nullable|exists:materiels,id',
            'autre_materiel_nom'   => 'nullable|string|max:255',
            'commentaire'          => 'nullable|string',
            'lieu_stockage_id'     => 'nullable|exists:lieu_stockages,id',
            'localisation_actuelle' => 'nullable|string|in:chantier,maintenance,atelier_maintenance',
        ]);

        $isDispo = empty($validated['materiel_id_associe']) && empty($validated['autre_materiel_nom']);

        $updateData = [
            'nom_article'         => $validated['nom_article'],
            'reference'           => $validated['reference'],
            'numero_serie'        => $validated['numero_serie'] ?? null,
            'etat'                => $validated['etat'],
            'date_acquisition'    => $validated['date_acquisition'],
            'materiel_id_associe' => $validated['materiel_id_associe'] ?? null,
            'autre_materiel_nom'  => $validated['autre_materiel_nom'] ?? null,
            'commentaire'         => $validated['commentaire'] ?? null,
            'is_dispo'           => $isDispo,
        ];

        if ($isDispo) {
            // Passage en stock
            $updateData['lieu_stockage_id']      = $validated['lieu_stockage_id'] ?? null;
            $updateData['localisation_actuelle'] = null;
            $updateData['date_retour_stock']     = now();
            $updateData['date_sortie_stock']     = null;
        } else {
            // Passage en utilisation
            $updateData['lieu_stockage_id']      = null;
            $updateData['localisation_actuelle'] = $validated['localisation_actuelle'] ?? 'chantier';
            // Si elle était en stock et devient associée, on fixe la date de sortie
            if ($fourniture->is_dispo) {
                $updateData['date_sortie_stock'] = now();
                $updateData['date_retour_stock'] = null;
            }
        }

        $fourniture->update($updateData);

        return response()->json([
            'message' => 'Fourniture mise à jour avec succès',
            'data'    => $fourniture->fresh()->load(['materiel', 'lieuStockage']),
        ]);
    }

    /**
     * Supprimer une fourniture (uniquement si disponible).
     */
    public function destroy($id)
    {
        $fourniture = Fourniture::find($id);

        if (!$fourniture) {
            return response()->json(['message' => 'Fourniture introuvable'], 404);
        }

        if (!$fourniture->is_dispo) {
            return response()->json([
                'message' => 'Impossible de supprimer une fourniture en cours d\'utilisation',
            ], 400);
        }

        $fourniture->delete();

        return response()->json(['message' => 'Fourniture supprimée avec succès']);
    }

    /**
     * Actions dynamiques sur une fourniture (affecter, transfert, retrait, hors_service, vérification).
     */
    public function actionFourniture(Request $request)
    {
        $validated = $request->validate([
            'fourniture_id'       => 'required|exists:fournitures,id',
            'action_type'         => 'required|string|in:affecter,transfert,retrait,hors_service,verification',
            'materiel_id'         => 'nullable|exists:materiels,id',
            'autre_materiel_nom'  => 'nullable|string|max:255',
            'observations'        => 'nullable|string',
            'etat'                => 'required|string|in:neuf,bon,moyen,a_verifier,hors_service',
            'localisation'        => 'nullable|string|in:chantier,maintenance,atelier_maintenance',
            'lieu_stockage_id'    => 'nullable|exists:lieu_stockages,id',
        ]);

        $fourniture = Fourniture::with('materiel')->findOrFail($validated['fourniture_id']);
        $action = $validated['action_type'];

        // Données communes
        $nouveauMaterielId   = $validated['materiel_id'] ?? null;
        $autreMaterielNom    = $validated['autre_materiel_nom'] ?? null;
        $nouvelleLocalisation = $validated['localisation'] ?? null;
        $nouveauLieuStockageId = $validated['lieu_stockage_id'] ?? null;

        switch ($action) {
            case 'affecter':
                // Vérifier qu'au moins une destination est fournie
                if (empty($nouveauMaterielId) && empty($autreMaterielNom)) {
                    return response()->json([
                        'message' => 'Veuillez sélectionner un véhicule ou saisir un autre matériel.'
                    ], 400);
                }

                // La fourniture doit être disponible
                if (!$fourniture->is_dispo) {
                    return response()->json(['message' => 'La fourniture est déjà utilisée.'], 400);
                }

                // État incompatible
                if (in_array($fourniture->etat, ['a_verifier', 'hors_service'])) {
                    return response()->json([
                        'message' => 'Cette fourniture nécessite une vérification avant utilisation.'
                    ], 400);
                }

                $fourniture->update([
                    'is_dispo'            => false,
                    'materiel_id_associe' => $nouveauMaterielId,
                    'autre_materiel_nom'  => $autreMaterielNom,
                    'lieu_stockage_id'    => null,
                    'localisation_actuelle' => $nouvelleLocalisation ?? 'chantier',
                    'date_sortie_stock'   => now(),
                    'date_retour_stock'   => null,
                    'etat'               => $validated['etat'],
                    'commentaire'        => $validated['observations'] ?? $fourniture->commentaire,
                ]);

                // Historique
                HistoriqueFourniture::create([
                    'fourniture_id' => $fourniture->id,
                    'type_action'   => 'affectation',
                    'date_action'   => now(),
                    'ancien_materiel_id'   => null,
                    'ancien_materiel_nom'  => null,
                    'nouveau_materiel_id'  => $nouveauMaterielId,
                    'nouveau_materiel_nom' => $nouveauMaterielId
                        ? optional(Materiel::find($nouveauMaterielId))->nom_materiel
                        : $autreMaterielNom,
                    'commentaire'   => $validated['observations'] ?? 'Affectation au matériel',
                    'etat'          => $validated['etat'],
                ]);

                return response()->json([
                    'message' => 'Fourniture affectée avec succès.',
                    'data'    => $fourniture->load('lieuStockage'),
                ], 200);

            case 'transfert':
                // La fourniture doit être actuellement utilisée
                if ($fourniture->is_dispo) {
                    return response()->json(['message' => 'La fourniture n\'est pas sur un matériel.'], 400);
                }

                if (empty($nouveauMaterielId) && empty($autreMaterielNom)) {
                    return response()->json(['message' => 'Veuillez spécifier la nouvelle destination.'], 400);
                }

                $ancienNom = $fourniture->materielAssocieNom;

                $fourniture->update([
                    'materiel_id_associe' => $nouveauMaterielId,
                    'autre_materiel_nom'  => $autreMaterielNom,
                    'lieu_stockage_id'    => null,
                    'localisation_actuelle' => $nouvelleLocalisation ?? $fourniture->localisation_actuelle,
                    'etat'               => $validated['etat'],
                    'commentaire'        => $validated['observations'] ?? $fourniture->commentaire,
                ]);

                HistoriqueFourniture::create([
                    'fourniture_id' => $fourniture->id,
                    'type_action'   => 'transfert',
                    'date_action'   => now(),
                    'ancien_materiel_id'   => $fourniture->materiel_id_associe,
                    'ancien_materiel_nom'  => $ancienNom,
                    'nouveau_materiel_id'  => $nouveauMaterielId,
                    'nouveau_materiel_nom' => $nouveauMaterielId
                        ? optional(Materiel::find($nouveauMaterielId))->nom_materiel
                        : $autreMaterielNom,
                    'commentaire'   => $validated['observations'] ?? 'Transfert de matériel',
                    'etat'          => $validated['etat'],
                ]);

                return response()->json([
                    'message' => 'Fourniture transférée avec succès.',
                    'data'    => $fourniture->load('lieuStockage'),
                ], 200);

            case 'retrait':
                if ($fourniture->is_dispo) {
                    return response()->json(['message' => 'La fourniture n\'est pas sur un matériel.'], 400);
                }

                $ancienNom = $fourniture->materielAssocieNom;

                // Selon l'état, on dirige vers stock ou atelier maintenance
                if (in_array($validated['etat'], ['a_verifier', 'hors_service'])) {
                    // Retour atelier maintenance (indisponible)
                    $fourniture->update([
                        'is_dispo'            => false,
                        'materiel_id_associe' => null,
                        'autre_materiel_nom'  => null,
                        'lieu_stockage_id'    => null,
                        'localisation_actuelle' => 'atelier_maintenance',
                        'date_retour_stock'   => now(),
                        'etat'               => $validated['etat'],
                        'commentaire'        => $validated['observations'] ?? $fourniture->commentaire,
                    ]);
                } else {
                    // Retour en stock (disponible)
                    $fourniture->update([
                        'is_dispo'            => true,
                        'materiel_id_associe' => null,
                        'autre_materiel_nom'  => null,
                        'lieu_stockage_id'    => $nouveauLieuStockageId, // obligatoire côté front ?
                        'localisation_actuelle' => null,
                        'date_retour_stock'   => now(),
                        'etat'               => $validated['etat'],
                        'commentaire'        => $validated['observations'] ?? $fourniture->commentaire,
                    ]);
                }

                HistoriqueFourniture::create([
                    'fourniture_id' => $fourniture->id,
                    'type_action'   => 'retrait',
                    'date_action'   => now(),
                    'ancien_materiel_id'   => $fourniture->materiel_id_associe,
                    'ancien_materiel_nom'  => $ancienNom,
                    'nouveau_materiel_id'  => null,
                    'nouveau_materiel_nom' => null,
                    'commentaire'   => $validated['observations'] ?? 'Retrait du matériel',
                    'etat'          => $validated['etat'],
                ]);

                return response()->json([
                    'message' => 'Fourniture retirée avec succès.',
                    'data'    => $fourniture->load('lieuStockage'),
                ], 200);

            case 'hors_service':
                $ancienNom = $fourniture->materielAssocieNom;

                $fourniture->update([
                    'is_dispo'            => false,
                    'materiel_id_associe' => null,
                    'autre_materiel_nom'  => null,
                    'lieu_stockage_id'    => null,
                    'localisation_actuelle' => 'atelier_maintenance',
                    'etat'               => 'hors_service',
                    'commentaire'        => $validated['observations'] ?? $fourniture->commentaire,
                ]);

                HistoriqueFourniture::create([
                    'fourniture_id' => $fourniture->id,
                    'type_action'   => 'mise_hors_service',
                    'date_action'   => now(),
                    'ancien_materiel_id'   => $fourniture->materiel_id_associe,
                    'ancien_materiel_nom'  => $ancienNom,
                    'nouveau_materiel_id'  => null,
                    'nouveau_materiel_nom' => null,
                    'commentaire'   => $validated['observations'] ?? 'Mise hors service',
                    'etat'          => 'hors_service',
                ]);

                return response()->json([
                    'message' => 'Fourniture mise hors service avec succès.',
                    'data'    => $fourniture->load('lieuStockage'),
                ], 200);

            case 'verification':
                $ancienEtat = $fourniture->etat;

                $fourniture->update([
                    'etat'       => $validated['etat'],
                    'commentaire' => $validated['observations'] ?? $fourniture->commentaire,
                ]);

                HistoriqueFourniture::create([
                    'fourniture_id' => $fourniture->id,
                    'type_action'   => 'verification',
                    'date_action'   => now(),
                    'ancien_materiel_id'   => $fourniture->materiel_id_associe,
                    'ancien_materiel_nom'  => $fourniture->materielAssocieNom,
                    'nouveau_materiel_id'  => $fourniture->materiel_id_associe,
                    'nouveau_materiel_nom' => $fourniture->materielAssocieNom,
                    'commentaire'   => 'Vérification: ' . $ancienEtat . ' → ' . $validated['etat'] . '. ' . ($validated['observations'] ?? ''),
                    'etat'          => $validated['etat'],
                ]);

                return response()->json([
                    'message' => 'Vérification effectuée avec succès.',
                    'data'    => $fourniture->load('lieuStockage'),
                ], 200);

            default:
                return response()->json(['message' => 'Action invalide.'], 400);
        }
    }
}
