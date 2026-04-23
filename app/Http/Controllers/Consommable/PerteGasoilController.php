<?php

namespace App\Http\Controllers\Consommable;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePerteGasoilRequest;
use App\Http\Requests\StorePerteGasoilSoirRequest;
use App\Models\Parametre\Materiel;
use App\Models\PerteGasoil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerteGasoilController extends Controller
{
    /**
     * Récupère la liste des pertes de gasoil avec pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $materielId = $request->get('materiel_id');
            $dateDebut = $request->get('date_debut');
            $dateFin = $request->get('date_fin');

            $query = PerteGasoil::with(['materiel:id,nom_materiel'])
                ->orderBy('created_at', 'desc');

            // Filtre par recherche
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('materiel', function ($subQuery) use ($search) {
                        $subQuery->where('nom_materiel', 'like', "%{$search}%");
                    })
                        ->orWhere('raison_perte', 'like', "%{$search}%");
                });
            }

            // Filtre par matériel
            if (!empty($materielId)) {
                $query->where('materiel_id', $materielId);
            }

            // Filtre par date
            if (!empty($dateDebut)) {
                $query->whereDate('created_at', '>=', $dateDebut);
            }

            if (!empty($dateFin)) {
                $query->whereDate('created_at', '<=', $dateFin);
            }

            $pertes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $pertes,
                'message' => 'Liste des pertes de gasoil récupérée avec succès.'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des pertes de gasoil: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des pertes.'
            ], 500);
        }
    }

    /**
     * Enregistre les pertes de gasoil
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StorePerteGasoilRequest $request)
    {
        try {
            $validatedData = $request->validated();
            $modifications = $validatedData['modifications'];
            $utilisateurId = auth()->id(); // Récupérer l'ID de l'utilisateur connecté

            // Utilisation d'une transaction pour garantir l'intégrité des données
            DB::beginTransaction();

            $resultats = PerteGasoil::creerPertesDepuisFront($modifications);

            if (!empty($resultats['echecs'])) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Certaines modifications n\'ont pas pu être enregistrées.',
                    'succes' => $resultats['succes'],
                    'echecs' => $resultats['echecs']
                ], 422);
            }

            // Mettre à jour le gasoil actuel dans la table des matériels
            Materiel::withoutJourneeCheck(function () use ($modifications) {
                foreach ($modifications as $modif) {
                    $materiel = Materiel::find($modif['id']);
                    $materiel->actuelGasoil = $modif['gasoilMaj'];
                    $materiel->save();
                }
            });

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Les pertes de gasoil ont été enregistrées avec succès.',
                'data' => $resultats['succes']
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur lors de l\'enregistrement des pertes de gasoil: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'enregistrement des pertes de gasoil.',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Enregistre les pertes de gasoil du soir
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeSoir(StorePerteGasoilSoirRequest $request)
    {
        try {
            // Validation des données
            $validatedData = $request->validated();

            $pertes = $validatedData['pertes'];
            $utilisateurId = auth()->id();

            // Utilisation d'une transaction pour garantir l'intégrité des données
            DB::beginTransaction();

            $resultats = [
                'succes' => [],
                'echecs' => []
            ];

            foreach ($pertes as $perte) {
                try {
                    // Chercher si une perte existe déjà pour ce matériel aujourd'hui
                    $perteGasoil = PerteGasoil::where('materiel_id', $perte['materiel_id'])
                        ->whereDate('created_at', now()->toDateString())
                        ->first();

                    if ($perteGasoil) {
                        // Mettre à jour l'enregistrement existant avec les données du soir
                        $perteGasoil->update([
                            'quantite_precedente_soir' => $perte['quantite_precedente_soir'],
                            'quantite_actuelle_soir' => $perte['quantite_actuelle_soir'],
                            'quantite_perdue_soir' => $perte['quantite_perdue_soir'],
                            'raison_perte_soir' => $perte['raison_perte_soir'] ?? null,
                        ]);
                    } else {
                        // Créer un nouvel enregistrement avec les données du soir
                        // (les champs du matin resteront null)
                        $perteGasoil = PerteGasoil::create([
                            'materiel_id' => $perte['materiel_id'],
                            'quantite_precedente_soir' => $perte['quantite_precedente_soir'],
                            'quantite_actuelle_soir' => $perte['quantite_actuelle_soir'],
                            'quantite_perdue_soir' => $perte['quantite_perdue_soir'],
                            'raison_perte_soir' => $perte['raison_perte_soir'] ?? null,
                        ]);
                    }

                    // Mettre à jour le gasoil actuel dans la table des matériels
                    $materiel = Materiel::find($perte['materiel_id']);
                    if ($materiel) {
                        // Convertir en centimètres si nécessaire (selon votre logique)
                        // Ici, on suppose que quantite_actuelle_soir est en litres
                        // Vous devrez peut-être ajuster selon votre unité de mesure
                        $materiel->actuelGasoil = $perte['quantite_actuelle_soir'];
                        $materiel->save();
                    }

                    $resultats['succes'][] = [
                        'materiel_id' => $perte['materiel_id'],
                        'perte_gasoil_id' => $perteGasoil->id,
                    ];
                } catch (\Exception $e) {
                    $resultats['echecs'][] = [
                        'materiel_id' => $perte['materiel_id'],
                        'erreur' => $e->getMessage()
                    ];
                }
            }

            // Si certaines pertes ont échoué
            if (!empty($resultats['echecs'])) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Certaines pertes du soir n\'ont pas pu être enregistrées.',
                    'succes' => $resultats['succes'],
                    'echecs' => $resultats['echecs']
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Les pertes de gasoil du soir ont été enregistrées avec succès.',
                'data' => $resultats['succes']
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur lors de l\'enregistrement des pertes de gasoil du soir: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'enregistrement des pertes de gasoil du soir.',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Récupère une perte de gasoil spécifique
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $perte = PerteGasoil::with(['materiel:id,nom_materiel'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $perte
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Perte de gasoil non trouvée.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de la perte de gasoil: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.'
            ], 500);
        }
    }

    /**
     * Récupère les statistiques des pertes de gasoil
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistiques(Request $request)
    {
        try {
            $dateDebut = $request->get('date_debut', now()->startOfMonth());
            $dateFin = $request->get('date_fin', now()->endOfMonth());

            $statistiques = DB::table('perte_gasoils')
                ->join('materiels', 'perte_gasoils.materiel_id', '=', 'materiels.id')
                ->select(
                    'materiels.id',
                    'materiels.nom_materiel as nom',
                    DB::raw('COUNT(perte_gasoils.id) as nombre_pertes'),
                    DB::raw('SUM(perte_gasoils.quantite_perdue) as total_perdu'),
                    DB::raw('AVG(perte_gasoils.quantite_perdue) as moyenne_perte')
                )
                ->whereBetween('perte_gasoils.created_at', [$dateDebut, $dateFin])
                ->groupBy('materiels.id', 'materiels.nom_materiel')
                ->orderBy('total_perdu', 'desc')
                ->get();

            $totalGeneral = $statistiques->sum('total_perdu');

            return response()->json([
                'success' => true,
                'data' => [
                    'statistiques' => $statistiques,
                    'total_general' => $totalGeneral,
                    'periode' => [
                        'debut' => $dateDebut,
                        'fin' => $dateFin
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du calcul des statistiques.'
            ], 500);
        }
    }

    /**
     * Exporte les pertes de gasoil au format CSV
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function export(Request $request)
    {
        try {
            $dateDebut = $request->get('date_debut', now()->startOfMonth());
            $dateFin = $request->get('date_fin', now()->endOfMonth());

            $pertes = PerteGasoil::with(['materiel:id,nom_materiel'])
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->orderBy('created_at', 'desc')
                ->get();

            $fileName = 'pertes_gasoil_' . now()->format('Y-m-d_H-i') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ];

            $callback = function () use ($pertes) {
                $file = fopen('php://output', 'w');

                // En-têtes
                fputcsv($file, [
                    'ID',
                    'Date',
                    'Matériel',
                    'Quantité précédente (L)',
                    'Quantité actuelle (L)',
                    'Quantité perdue (L)',
                    'Raison de la perte'
                ]);

                // Données
                foreach ($pertes as $perte) {
                    fputcsv($file, [
                        $perte->id,
                        $perte->created_at->format('d/m/Y H:i'),
                        $perte->materiel->nom_materiel,
                        $perte->quantite_precedente,
                        $perte->quantite_actuelle,
                        $perte->quantite_perdue,
                        $perte->raison_perte
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'export des pertes: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'export.'
            ], 500);
        }
    }
}
