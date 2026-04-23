<?php

namespace App\Http\Controllers\Parametre;

use App\Exports\PneusExport;
use App\Http\Controllers\Controller;
use App\Models\Parametre\Pneu;
use App\Models\Parametre\Materiel;
use Error;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PneuController extends Controller
{
    /**
     * Afficher la liste de tous les pneus avec filtres
     */
    public function index(Request $request)
    {
        $query = Pneu::with('materiel:id,nom_materiel', 'lieuStockage:id,nom');

        // Filtre par recherche (num_serie, caractéristiques, marque, type, lieu de stockage)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('num_serie', 'like', '%' . $search . '%')
                    ->orWhere('caracteristiques', 'like', '%' . $search . '%')
                    ->orWhere('marque', 'like', '%' . $search . '%')
                    ->orWhere('type', 'like', '%' . $search . '%')
                    ->orWhereHas('materiel', function ($q) use ($search) {
                        $q->where('nom_materiel', 'like', '%' . $search . '%');
                    })->orWhereHas('lieuStockage', function ($q) use ($search) {
                        $q->where('nom', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filtre par date de début (date_obtention)
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('date_obtention', '>=', $request->date_start);
        }

        // Filtre par date de fin (date_obtention)
        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('date_obtention', '<=', $request->date_end);
        }

        // Filtre par état
        if ($request->has('etat') && !empty($request->etat)) {
            $query->where('etat', $request->etat);
        }

        // Filtre par situation
        if ($request->has('situation') && !empty($request->situation)) {
            $query->where('situation', $request->situation);
        }

        // Tri par défaut
        $query->orderBy('created_at', 'desc');

        $pneus = $query->get();

        return response()->json($pneus);
    }

    public function create()
    {
        $vehicule = Materiel::all();
        return response()->json($vehicule);
    }

    /**
     * Export Excel
     */
    public function exportExcel(Request $request)
    {
        $filters = $request->all();
        return Excel::download(new PneusExport($filters), 'pneus.xlsx');
    }

    /**
     * Vérifie la capacité et l'emplacement du pneu sur le véhicule
     */
    private function verifierCapaciteEtEmplacement($materiel_id, $emplacement, $pneu_id = null)
    {
        $errors = [];

        if (!$materiel_id) {
            return $errors; // Pas de vérification si pas de matériel
        }

        $materiel = Materiel::find($materiel_id);

        if (!$materiel) {
            $errors[] = 'Matériel non trouvé.';
            return $errors;
        }

        // Vérification de la capacité seulement pour véhicules et engins
        if (in_array($materiel->categorie, ['vehicule', 'engin'])) {
            $nbr_pneuTotal = $materiel->nbr_pneu ?? null;

            if ($nbr_pneuTotal !== null) {
                $query = $materiel->pneus();

                // Exclure le pneu actuel en cas de modification
                if ($pneu_id) {
                    $query->where('id', '!=', $pneu_id);
                }

                $nbr_pneusInstall = $query->count();

                if ($nbr_pneusInstall >= $nbr_pneuTotal) {
                    $errors[] = 'Le nombre de pneus installé sur ' . $materiel->nom_materiel . ' est déjà complet.';
                }
            }
        }

        // Vérification de l'emplacement unique
        if (!empty($emplacement)) {
            $query = Pneu::where('materiel_id', $materiel_id)
                ->where('emplacement', $emplacement);

            // Exclure le pneu actuel en cas de modification
            if ($pneu_id) {
                $query->where('id', '!=', $pneu_id);
            }

            if ($query->exists()) {
                $errors[] = 'Un pneu avec l\'emplacement "' . $emplacement . '" existe déjà sur le véhicule ' . $materiel->nom_materiel . '.';
            }
        }

        return $errors;
    }

    public function store(Request $request)
    {
        // validation
        $validatedData = $request->validate([
            'num_serie' => 'required|string|unique:pneus,num_serie',
            'date_obtention' => 'required|date',
            'date_mise_en_service' => 'nullable|date|after_or_equal:date_obtention',
            'date_mise_hors_service' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value) {
                        $refDate = $request->date_mise_en_service ?? $request->date_obtention;
                        if ($value < $refDate) {
                            $fail("La date de mise hors service doit être après la date de référence (" . $refDate . ").");
                        }
                    }
                },
            ],
            'etat' => 'required|string|in:bonne,usée,défectueuse',
            'caracteristiques' => 'required|string|max:100',
            'marque' => 'required|string|max:50',
            'type' => 'required|string|max:50',
            'situation' => 'required|string|in:en_service,hors_service,en_reparation',
            'emplacement' => 'nullable|string|max:50',
            'observations' => 'nullable|string',
            'kilometrage' => 'required|integer',
            'materiel_id' => 'nullable|exists:materiels,id'
        ]);

        // Vérifications supplémentaires avant création
        $errors = $this->verifierCapaciteEtEmplacement(
            $validatedData['materiel_id'] ?? null,
            $validatedData['emplacement'] ?? null
        );

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $errors
            ], 422);
        }

        $pneu = new Pneu();
        $pneu->fill($validatedData);
        $pneu->save();

        return response()->json([
            'message' => 'Pneu ajouté avec succès.',
            'data' => $pneu,
        ], 201);
    }

    public function show($id)
    {
        $pneu = Pneu::with('materiel')->find($id);

        if (!$pneu) {
            return response()->json(['message' => 'Pneu introuvable'], 404);
        }

        return response()->json($pneu);
    }

    public function edit($id)
    {
        $pneu = Pneu::find($id);

        if (!$pneu) {
            return response()->json(['message' => 'Pneu introuvable'], 404);
        }

        return response()->json($pneu);
    }

    public function update(Request $request, $id)
    {
        $pneu = Pneu::find($id);

        if (!$pneu) {
            return response()->json(['message' => 'Pneu introuvable'], 404);
        }

        $request->validate([
            'num_serie' => 'required|string|unique:pneus,num_serie,' . $id,
            'date_obtention' => 'required|date',
            'date_mise_en_service' => 'nullable|date|after_or_equal:date_obtention',
            'date_mise_hors_service' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value) {
                        $refDate = $request->date_mise_en_service ?? $request->date_obtention;
                        if ($value < $refDate) {
                            $fail("La date de mise hors service doit être après la date de référence (" . $refDate . ").");
                        }
                    }
                },
            ],
            'etat' => 'required|string|in:bonne,usée,défectueuse,endommagée',
            'caracteristiques' => 'required|string|max:100',
            'marque' => 'required|string|max:50',
            'type' => 'required|string|max:50',
            'situation' => 'required|string|in:en_service,hors_service,en_reparation',
            'emplacement' => 'nullable|string|max:50',
            'observations' => 'nullable|string',
            'kilometrage' => 'required|integer',
            'materiel_id' => 'nullable|exists:materiels,id'
        ]);

        // Vérifications supplémentaires avant mise à jour
        $errors = $this->verifierCapaciteEtEmplacement(
            $request->materiel_id ?? null,
            $request->emplacement ?? null,
            $id // Exclure le pneu actuel de la vérification
        );

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $errors
            ], 422);
        }

        $pneu->update($request->all());

        return response()->json([
            'message' => 'Pneu mis à jour avec succès',
            'data' => $pneu
        ]);
    }

    public function destroy($id)
    {
        $pneu = Pneu::find($id);

        if (!$pneu) {
            return response()->json(['message' => 'Pneu introuvable'], 404);
        }

        $pneu->delete();

        return response()->json(['message' => 'Pneu supprimé avec succès']);
    }

    public function actionPneu(Request $request)
    {
        $validatedData = $request->validate([
            'pneu_id' => 'required|exists:pneus,id',
            'action_type' => 'required|string|in:affecter,transfert,retrait,hors_service',
            'materiel_id' => 'nullable|exists:materiels,id',
            'observations' => 'nullable|string',
            'sous_action' => 'nullable|string|in:stock,reparation',
            'etat' => 'required|string|in:bonne,usée,défectueuse,endommagée',
            'emplacement' => 'nullable|string',
            'lieu_stockage_id' => 'nullable|exists:lieu_stockages,id',
        ]);

        $pneu = Pneu::findOrFail($validatedData['pneu_id']);
        $action = $validatedData['action_type'];

        $nouveauMaterielId = $validatedData['materiel_id'] ?? null;
        $nouveauMaterielNom = optional(Materiel::find($nouveauMaterielId))->nom_materiel;

        $ancienMaterielId = $pneu->materiel_id;
        $ancienMaterielNom = optional($pneu->materiel)->nom_materiel;

        switch ($action) {
            case 'affecter':
                if (!$pneu->materiel_id && $nouveauMaterielId) {

                    // Vérifications supplémentaires avant affectation
                    $errors = $this->verifierCapaciteEtEmplacement(
                        $nouveauMaterielId,
                        $validatedData['emplacement'] ?? null,
                        $pneu->id
                    );

                    if (!empty($errors)) {
                        return response()->json([
                            'message' => 'Erreur lors de l\'affectation',
                            'errors' => $errors
                        ], 422);
                    }

                    // Mettre à jour l'état ET affecter au matériel
                    $pneu->update([
                        'materiel_id' => $nouveauMaterielId,
                        'etat' => $validatedData['etat'],
                        'situation' => 'en_service', // S'assurer qu'il est en service
                        'observations' => $validatedData['observations'] ?? 'Pas d\'observation',
                        'emplacement' => $validatedData['emplacement'],
                        'lieu_stockage_id' => null,
                    ]);

                    $pneu->load('materiel');
                    $pneu->historiques()->create([
                        'type_action' => 'ajout',
                        'date_action' => now(),
                        'ancien_materiel_id' => null,
                        'ancien_materiel_nom' => null,
                        'nouveau_materiel_id' => $nouveauMaterielId,
                        'nouveau_materiel_nom' => $nouveauMaterielNom,
                        'commentaire' => 'Affectation du pneu au matériel ' . $nouveauMaterielNom . '. Observation :' . ($validatedData['observations'] ?? 'N/A'),
                        'etat' => $validatedData['etat']
                    ]);
                    return response()->json(['message' => 'Pneu affecté avec succès.', 'data' => $pneu], 200);
                }
                return response()->json(['message' => 'Le pneu est déjà affecté ou matériel non fourni.'], 400);

            case 'transfert':
                if ($pneu->materiel_id && $nouveauMaterielId) {

                    // Vérifications supplémentaires avant transfert
                    $errors = $this->verifierCapaciteEtEmplacement(
                        $nouveauMaterielId,
                        $validatedData['emplacement'] ?? null,
                        $pneu->id
                    );

                    if (!empty($errors)) {
                        return response()->json([
                            'message' => 'Erreur lors du transfert',
                            'errors' => $errors
                        ], 422);
                    }

                    // Mettre à jour l'état ET transférer vers nouveau matériel
                    $pneu->update([
                        'materiel_id' => $nouveauMaterielId,
                        'etat' => $validatedData['etat'],
                        'observations' => $validatedData['observations'] ?? 'Pas d\'observation',
                        'emplacement' => $validatedData['emplacement'],
                        'lieu_stockage_id' => null,
                    ]);

                    $pneu->load('materiel');
                    $pneu->historiques()->create([
                        'type_action' => 'transfert',
                        'date_action' => now(),
                        'ancien_materiel_id' => $ancienMaterielId,
                        'ancien_materiel_nom' => $ancienMaterielNom,
                        'nouveau_materiel_id' => $nouveauMaterielId,
                        'nouveau_materiel_nom' => $nouveauMaterielNom,
                        'commentaire' => 'Transfert du pneu de ' . $ancienMaterielNom . ' vers : ' . $nouveauMaterielNom . '. Observation :' . ($validatedData['observations'] ?? 'N/A'),
                        'etat' => $validatedData['etat']
                    ]);
                    return response()->json(['message' => 'Pneu transféré avec succès.', 'data' => $pneu], 200);
                }
                return response()->json(['message' => 'Transfert impossible. Vérifiez les matériels.'], 400);

            case 'retrait':
                try {
                    // Validation des champs supplémentaires pour le retrait
                    if (empty($validatedData['sous_action'])) {
                        return response()->json(['message' => 'Veuillez spécifier le type de retrait (stock ou réparation).'], 400);
                    }

                    if (empty($validatedData['observations'])) {
                        return response()->json(['message' => 'Les observations sont obligatoires pour le retrait.'], 400);
                    }

                    if ($validatedData['sous_action'] === 'stock' && empty($validatedData['lieu_stockage_id'])) {
                        return response()->json([
                            'message' => 'Le lieu de stockage est obligatoire pour une mise en stock.'
                        ], 422);
                    }

                    // Déterminer la nouvelle situation
                    $nouvelleSituation = $validatedData['sous_action'] === 'reparation' ? 'en_reparation' : 'en_stock';

                    // Mettre à jour le pneu
                    $pneu->update([
                        'materiel_id' => null,
                        'situation' => $nouvelleSituation,
                        'etat' => $validatedData['etat'],
                        'observations' => $validatedData['observations'],
                        'lieu_stockages_id' => $validatedData['sous_action'] === 'stock'
                            ? $validatedData['lieu_stockage_id']
                            : null,
                    ]);

                    $pneu->load('materiel');

                    // Créer l'entrée d'historique
                    $typeAction = $validatedData['sous_action'] === 'reparation' ? 'reparation' : 'retrait';
                    $commentaire = $validatedData['sous_action'] === 'reparation'
                        ? 'Retrait pour réparation'
                        : 'Retrait et mise en stock';

                    $pneu->historiques()->create([
                        'type_action' => $typeAction,
                        'date_action' => now(),
                        'ancien_materiel_id' => $ancienMaterielId ?? null,
                        'ancien_materiel_nom' => $ancienMaterielNom ?? null,
                        'nouveau_materiel_id' => null,
                        'nouveau_materiel_nom' => null,
                        'commentaire' => $commentaire . ' - ' . $validatedData['observations'],
                        'etat' => $validatedData['etat']
                    ]);

                    $message = $validatedData['sous_action'] === 'reparation'
                        ? 'Pneu retiré et mis en réparation avec succès.'
                        : 'Pneu retiré et mis en stock avec succès.';

                    return response()->json(['message' => $message, 'data' => $pneu], 200);
                } catch (Error $err) {
                    return response()->json(['message' => 'Le pneu n\'est pas affecté à un matériel : ' . $err], 400);
                }

            case 'hors_service':
                // Sauvegarder l'ancien matériel avant de le retirer
                $ancienMaterielId = $pneu->materiel_id;
                $ancienMaterielNom = optional($pneu->materiel)->nom_materiel;

                // Mettre à jour le pneu : retirer du matériel, changer situation, état, date et observations
                $pneu->update([
                    'materiel_id' => null,
                    'situation' => 'hors_service',
                    'etat' => 'endommagée', // Forcer l'état à "endommagée" pour hors service
                    'date_mise_hors_service' => now(),
                    'observations' => $validatedData['observations'] ?? $pneu->observations,
                    'lieu_stockage_id' => null,
                ]);

                $pneu->load('materiel');

                // Créer l'entrée d'historique
                $pneu->historiques()->create([
                    'type_action' => 'mise_hors_service',
                    'date_action' => now(),
                    'ancien_materiel_id' => $ancienMaterielId,
                    'ancien_materiel_nom' => $ancienMaterielNom,
                    'nouveau_materiel_id' => null,
                    'nouveau_materiel_nom' => null,
                    'commentaire' => $validatedData['observations'] ?? 'Mise hors service sans observation',
                    'etat' => 'endommagée', // État forcé pour hors service
                ]);

                return response()->json(['message' => 'Pneu mis hors service et retiré du matériel avec succès.', 'data' => $pneu], 200);

            default:
                return response()->json(['message' => 'Action invalide.'], 400);
        }
    }
}
