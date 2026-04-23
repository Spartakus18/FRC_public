<?php

namespace App\Http\Controllers;

use App\Models\CompteurJournee;
use App\Models\Consommable\Gasoil;
use App\Models\GasoilJournee;
use App\Models\Journee;
use App\Models\Parametre\Materiel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MessageFormatter;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class JourneeController extends Controller
{
    /**
     * Récupère l'état de la journée d'aujourd'hui
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statut()
    {
        try {
            $journee = Journee::journeeAujourdhui();

            $statut = [
                'journee_existe' => $journee !== null,
                'isBegin' => $journee ? $journee->isBegin : false,
                'isEnd' => $journee ? $journee->isEnd : false,
                'date' => now()->toDateString(),
                'peut_demarrer' => Journee::peutDemarrerJournee(),
                'peut_terminer' => Journee::peutTerminerJournee(),
                'peut_reactiver' => Journee::peutReactiverJournee(),
            ];

            if ($journee) {
                $statut['details'] = [
                    'id' => $journee->id,
                    'demarree_par' => $journee->userStart->nom ?? 'Inconnu',
                    'terminee_par' => $journee->userEnd->nom ?? null,
                    'date_creation' => $journee->created_at->format('d/m/Y H:i'),
                    'notes' => $journee->notes,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $statut,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération du statut de la journée: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du statut de la journée.',
            ], 500);
        }
    }

    /**
     * Démarre une nouvelle journée
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function demarrer(Request $request)
    {
        try {
            $request->validate([
                'notes' => 'nullable|string|max:1000',
            ]);

            $userId = auth()->id();
            $notes = $request->input('notes');

            // Vérifier si on peut démarrer une journée
            $verification = Journee::peutDemarrerJournee();

            if (!$verification['peut_demarrer']) {
                return response()->json([
                    'success' => false,
                    'message' => $verification['message'],
                ], 400);
            }

            // Démarrer la journée
            $journee = Journee::demarrerJournee($userId, $notes);

            Log::info('Journée démarrée par l\'utilisateur ID: ' . $userId, [
                'journee_id' => $journee->id,
                'date' => $journee->date,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Journée démarrée avec succès.',
                'data' => [
                    'journee' => $journee,
                    'user_start' => $journee->userStart,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors du démarrage de la journée: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Termine la journée d'aujourd'hui
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function terminer(Request $request)
    {
        try {
            $request->validate([
                'notes' => 'nullable|string|max:1000',
            ]);

            $userId = auth()->id();
            $notes = $request->input('notes');

            // Vérifier si on peut terminer une journée
            $verification = Journee::peutTerminerJournee();

            if (!$verification['peut_terminer']) {
                return response()->json([
                    'success' => false,
                    'message' => $verification['message'],
                ], 400);
            }

            // Terminer la journée
            $journee = Journee::journeeAujourdhui();
            Journee::terminerJournee($userId, $notes);

            return response()->json([
                'success' => true,
                'message' => 'Journée terminée avec succès.',
                'data' => [
                    'journee' => $journee->fresh(), // Recharger les données fraîches
                    'user_end' => $journee->userEnd,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la fin de la journée: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function terminerJournee(Request $request, Journee $journee)
    {
        abort_if(!$journee->isBegin || $journee->isEnd, 403, 'Journée invalide');

        $request->validate([
            'materiels' => 'required|array',
            'materiels.*.materiel_id' => 'required|exists:materiels,id',
            'materiels.*.gasoilFin' => 'required|numeric|min:0',
            'materiels.*.compteurFin' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        DB::transaction(function () use ($request, $journee) {

            foreach ($request->materiels as $item) {
                $gasoilSnapshot = GasoilJournee::where('journee_id', $journee->id)
                    ->where('materiel_id', $item['materiel_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $compteurSnapshot = CompteurJournee::where('journee_id', $journee->id)
                    ->where('materiel_id', $item['materiel_id'])
                    ->lockForUpdate()
                    ->first();

                $materiel = Materiel::query()
                    ->where('id', $item['materiel_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                // 1. Enregistrer gasoil soir
                $gasoilSnapshot->update([
                    'gasoil_soir' => $item['gasoilFin'],
                    'consommation' => (float) $gasoilSnapshot->gasoil_matin - (float) $item['gasoilFin'],
                ]);

                $compteurSoir = array_key_exists('compteurFin', $item) && $item['compteurFin'] !== null
                    ? (float) $item['compteurFin']
                    : $materiel->compteur_actuel;

                if ($compteurSnapshot) {
                    $variationCompteur = $compteurSoir !== null
                        ? (float) $compteurSoir - (float) $compteurSnapshot->compteur_matin
                        : null;

                    $compteurSnapshot->update([
                        'compteur_soir' => $compteurSoir,
                        'variation' => $variationCompteur,
                        'has_compteur' => (bool) $compteurSnapshot->has_compteur || ($variationCompteur !== null && $variationCompteur != 0),
                    ]);
                }

                // 2. Mettre à jour le matériel
                $payloadMateriel = [
                    'actuelGasoil' => $item['gasoilFin'],
                ];

                if (array_key_exists('compteurFin', $item) && $item['compteurFin'] !== null) {
                    $payloadMateriel['compteur_actuel'] = $compteurSoir;
                }

                $materiel->update($payloadMateriel);
            }

            // 3. Clôturer la journée
            $journee->update([
                'isEnd' => true,
                'user_id_end' => auth()->id(),
                'notes' => $request->notes,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Journée clôturée avec succès',
        ]);
    }


    /**
     * Ré-activer la journée d'aujourd'hui
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reactiver(Request $request)
    {
        $user = Auth::user();

        // Vérifier si l'utilisateur est administrateur (role_id = 1)
        if ($user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée'
            ], 403);
        }

        try {
            // Vérifier si on peut réactiver la journée
            $verification = Journee::peutReactiverJournee();

            if (!$verification['peut_reactiver']) {
                return response()->json([
                    'success' => false,
                    'message' => $verification['message']
                ], 400);
            }

            // Réactiver la journée
            Journee::reactiverJourneeAujourdhui();

            // Récupérer la journée mise à jour
            $journee = Journee::journeeAujourdhui();

            /* Log::info('Journée réactivée par l\'utilisateur ID: ' . $user->id, [
                'journee_id' => $journee->id,
                'date' => $journee->date,
            ]); */

            return response()->json([
                'success' => true,
                'message' => 'Journée réactivée avec succès.',
                'data' => [
                    'journee' => $journee,
                    'statut' => [
                        'isBegin' => $journee->isBegin,
                        'isEnd' => $journee->isEnd,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            /* Log::error('Erreur lors de la réactivation de la journée: ' . $e->getMessage()); */

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réactivation de la journée: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère l'historique des journées
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function historique(Request $request)
    {
        try {
            $request->validate([
                'date_debut' => 'required|date',
                'date_fin' => 'required|date|after_or_equal:date_debut',
                'par_page' => 'nullable|integer|min:1|max:100',
            ]);

            $dateDebut = $request->input('date_debut');
            $dateFin = $request->input('date_fin');
            $parPage = $request->input('par_page', 20);

            $journees = Journee::with(['userStart:id,nom', 'userEnd:id,nom'])
                ->whereBetween('date', [$dateDebut, $dateFin])
                ->orderBy('date', 'desc')
                ->paginate($parPage);

            // Ajouter des statistiques
            $statistiques = [
                'total' => $journees->total(),
                'demarrees' => Journee::whereBetween('date', [$dateDebut, $dateFin])->where('isBegin', true)->count(),
                'terminees' => Journee::whereBetween('date', [$dateDebut, $dateFin])->where('isEnd', true)->count(),
                'en_cours' => Journee::whereBetween('date', [$dateDebut, $dateFin])
                    ->where('isBegin', true)
                    ->where('isEnd', false)
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'journees' => $journees,
                    'statistiques' => $statistiques,
                    'periode' => [
                        'debut' => $dateDebut,
                        'fin' => $dateFin,
                    ],
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de l\'historique des journées: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique.',
            ], 500);
        }
    }

    /**
     * Récuperer les gasoils du journée
     * */
    public function gasoilJournee(Journee $journee)
    {
        $compteurParMateriel = CompteurJournee::where('journee_id', $journee->id)
            ->get()
            ->keyBy('materiel_id');

        $data = GasoilJournee::with(['materiel' => function ($query) {
            $query->orderBy('nom_materiel', 'asc');
        }])
            ->where('journee_id', $journee->id)
            ->get()
            ->sortBy(function ($item) {
                return $item->materiel->nom_materiel;
            })
            ->values()
            ->map(function ($journeeGasoil) use ($journee, $compteurParMateriel) {
                // Somme des gasoils versés pour CE matériel pendant LA journée
                $gasoilAjoute = Gasoil::where('materiel_id_cible', $journeeGasoil->materiel_id)
                    ->where('type_operation', 'versement')
                    ->where('is_consumed', 1)
                    ->whereDate('created_at', $journee->date)
                    ->sum('quantite');

                $compteurSnapshot = $compteurParMateriel->get($journeeGasoil->materiel_id);
                $compteurMatin = $compteurSnapshot ? (float) $compteurSnapshot->compteur_matin : null;
                $compteurActuel = $journeeGasoil->materiel->compteur_actuel;
                $compteurDifference = ($compteurMatin !== null && $compteurActuel !== null)
                    ? (float) $compteurActuel - $compteurMatin
                    : null;

                return [
                    'materiel_id'   => $journeeGasoil->materiel->id,
                    'nom'           => $journeeGasoil->materiel->nom_materiel,
                    'gasoil_matin'  => (float) $journeeGasoil->gasoil_matin,
                    'gasoil_ajoute' => (float) $gasoilAjoute,
                    'difference'    => (float) $gasoilAjoute,
                    'gasoilActuel' => $journeeGasoil->materiel->actuelGasoil,
                    'compteur_matin' => $compteurMatin,
                    'compteurActuel' => $compteurActuel,
                    'compteur_difference' => $compteurDifference,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * CALCULE LE BILAN DE GAZOIL EN FIN DE JOURNÉE
     *
     * Cette méthode analyse les variations de gasoil pour chaque matériel
     * et inclut également les matériels marqués has_gasoil même sans variation.
     *
     * PRINCIPES D'INCLUSION :
     * 1. Les matériels avec variation de niveau (gasoilMatin != gasoilActuel)
     * 2. Les matériels sans variation mais marqués has_gasoil = true
     * 3. Exclut les autres matériels sans variation et non marqués
     *
     * @param \App\Models\Journee $journee Instance de la journée à analyser
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     * @code 403 Si la journée n'est pas valide (non démarrée ou déjà terminée)
     *
     * STRUCTURE DE LA RÉPONSE JSON :
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "materiel_id": int,
     *       "nom": string,
     *       "gasoilMatin": float,
     *       "gasoilAjoute": float,
     *       "gasoilActuel": float,
     *       "difference": float,
     *       "has_gasoil": boolean,
     *       "inclus_par": "variation"|"has_gasoil"  // Pourquoi ce matériel est inclus
     *     }
     *   ]
     * }
     */
    public function gasoilFin(Journee $journee)
    {
        // VALIDATION DE LA JOURNÉE
        abort_if(!$journee->isBegin || $journee->isEnd, 403, 'Journée invalide');

        // RÉCUPÉRATION DE TOUS LES ENREGISTREMENTS GASOILJOURNEE POUR CETTE JOURNÉE
        $gasoilJournees = GasoilJournee::query()
            ->join('materiels', 'materiels.id', '=', 'gasoil_journees.materiel_id')
            ->where('gasoil_journees.journee_id', $journee->id)
            ->select([
                'gasoil_journees.materiel_id',
                'gasoil_journees.has_gasoil',
                'gasoil_journees.gasoil_matin',
                'materiels.nom_materiel as nom',
                'materiels.actuelGasoil as gasoilActuel',
                'materiels.compteur_actuel as compteurActuel',
            ])
            ->get();

        $compteurJournees = CompteurJournee::query()
            ->where('journee_id', $journee->id)
            ->get()
            ->keyBy('materiel_id');

        // CALCUL DES AJOUTS POUR CHAQUE MATÉRIEL ET FILTRAGE
        $data = $gasoilJournees->map(function ($item) use ($journee, $compteurJournees) {
            // Somme des gasoils versés CE jour pour CE matériel
            $gasoilAjoute = Gasoil::where('materiel_id_cible', $item->materiel_id)
                ->where('type_operation', 'versement')
                ->where('is_consumed', 1)
                ->whereDate('created_at', $journee->date)
                ->sum('quantite');

            $compteurSnapshot = $compteurJournees->get($item->materiel_id);
            $compteurMatin = $compteurSnapshot ? (float) $compteurSnapshot->compteur_matin : null;
            $compteurActuel = $item->compteurActuel !== null ? (float) $item->compteurActuel : null;
            $compteurDifference = ($compteurMatin !== null && $compteurActuel !== null)
                ? $compteurActuel - $compteurMatin
                : null;

            // Calcul de la différence
            $difference = $item->gasoilActuel - $item->gasoil_matin;

            // Déterminer pourquoi ce matériel est inclus
            $hasVariation = $difference != 0;
            $isMarkedHasGasoil = (bool)$item->has_gasoil;

            // Déterminer la raison de l'inclusion
            if ($hasVariation && $isMarkedHasGasoil) {
                $inclusPar = 'variation_et_has_gasoil';
            } elseif ($hasVariation) {
                $inclusPar = 'variation';
            } else {
                $inclusPar = 'has_gasoil';
            }

            return [
                'materiel_id'  => $item->materiel_id,
                'nom'          => $item->nom,
                'gasoilMatin'  => $item->gasoil_matin,
                'gasoilAjoute' => $gasoilAjoute,
                'gasoilActuel' => $item->gasoilActuel,
                'difference'   => $difference,
                'has_gasoil'   => $isMarkedHasGasoil,
                'inclus_par'   => $inclusPar,
                'compteurMatin' => $compteurMatin,
                'compteurActuel' => $compteurActuel,
                'compteurDifference' => $compteurDifference,
                'has_compteur' => (bool) ($compteurSnapshot->has_compteur ?? false),
            ];
        })
            // FILTRER : garder seulement les matériels qui répondent aux critères
            ->filter(function ($item) {
                // Garder si variation/flag gasoil OU variation/flag compteur
                return $item['difference'] != 0
                    || $item['has_gasoil'] === true
                    || $item['compteurDifference'] != 0
                    || $item['has_compteur'] === true;
            })
            ->values(); // Réindexer le tableau

        // RÉPONSE JSON STRUCTURÉE
        return response()->json([
            'success' => true,
            'data' => $data,
            'metadata' => [
                'total_materiels' => $gasoilJournees->count(),
                'materiels_inclus' => $data->count(),
                'par_variation' => $data->where('inclus_par', 'variation')->count(),
                'par_has_gasoil' => $data->where('inclus_par', 'has_gasoil')->count(),
                'par_les_deux' => $data->where('inclus_par', 'variation_et_has_gasoil')->count(),
                'par_variation_compteur' => $data->filter(fn($item) => $item['compteurDifference'] != 0)->count(),
                'par_has_compteur' => $data->where('has_compteur', true)->count(),
            ]
        ]);
    }

    /**
     * RÉCUPÈRE ET REGROUPE TOUTES LES OPÉRATIONS DES MATÉRIELS PENDANT UNE JOURNÉE
     *
     * Cette méthode collecte toutes les opérations (Production, Livraison, Transfert) réalisées
     * par chaque matériel pendant la journée spécifiée et les regroupe par matériel.
     *
     * STRUCTURE DES DONNÉES RETOURNÉES :
     * [
     *   {
     *     "materiel_id": int,
     *     "nom_materiel": string,
     *     "productions": [
     *       {
     *         "id": int,
     *         "heure_debut": datetime,
     *         "heure_fin": datetime,
     *         "compteur_debut": float,
     *         "compteur_fin": float,
     *         "gasoil_debut": float,
     *         "gasoil_fin": float,
     *         "consommation_reelle_par_heure": float,
     *         "consommation_totale": float,
     *         "observation": string
     *       }
     *     ],
     *     "livraisons": [
     *       {
     *         "id": int,
     *         "numBL": string,
     *         "heure_depart": datetime,
     *         "heure_arrive": datetime,
     *         "quantite": float,
     *         "client": string,
     *         "isDelivred": boolean
     *       }
     *     ],
     *     "transferts": [
     *       {
     *         "id": int,
     *         "heure_depart": datetime,
     *         "heure_arrivee": datetime,
     *         "quantite": float,
     *         "lieu_depart": string,
     *         "lieu_arrive": string,
     *         "produit": string
     *       }
     *     ]
     *   }
     * ]
     *
     * @param \App\Models\Journee $journee Instance de la journée à analyser
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     * @code 403 Si la journée n'est pas valide (non démarrée ou déjà terminée)
     *
     * NOTES IMPORTANTES :
     * - Les opérations sont filtrées par la date de la journée
     * - Les données sont regroupées par matériel pour une analyse facile
     * - Inclut les détails essentiels de chaque opération
     * - Permet d'analyser l'activité globale des matériels
     *
     * @see \App\Models\Produit\ProductionMateriel Pour les données de production
     * @see \App\Models\BL\BonLivraison Pour les données de livraison
     * @see \App\Models\Produit\TransfertProduit Pour les données de transfert
     * @see \App\Models\Parametre\Materiel Pour les données des matériels
     */
    /* public function operationsParMateriel(Journee $journee)
    {
        // VALIDATION DE LA JOURNÉE
        abort_if(!$journee->isBegin || $journee->isEnd, 403, 'Journée invalide');

        // RÉCUPÉRATION DES OPÉRATIONS PAR TYPE
        $productions = $this->getProductionsJournee($journee);
        $livraisons = $this->getLivraisonsJournee($journee);
        $transferts = $this->getTransfertsJournee($journee);

        // FUSION DES MATÉRIELS UNIQUES AVEC OPÉRATIONS
        $materielsIds = collect()
            ->merge($productions->pluck('materiel_id'))
            ->merge($livraisons->pluck('vehicule_id'))
            ->merge($transferts->pluck('materiel_id'))
            ->unique()
            ->filter();

        // CONSTRUCTION DES DONNÉES GROUPÉES PAR MATÉRIEL
        $data = $materielsIds->map(function ($materielId) use ($productions, $livraisons, $transferts, $journee) {

            // Récupération du nom du matériel
            $materiel = \App\Models\Parametre\Materiel::find($materielId);

            // Groupement des opérations par type
            return [
                'materiel_id'   => $materielId,
                'nom_materiel'  => $materiel->nom_materiel ?? 'Matériel inconnu',
                'productions'   => $productions->where('materiel_id', $materielId)->values(),
                'livraisons'    => $livraisons->where('vehicule_id', $materielId)->values(),
                'transferts'    => $transferts->where('materiel_id', $materielId)->values(),
                'resume'        => $this->calculerResumeOperations(
                    $productions->where('materiel_id', $materielId),
                    $livraisons->where('vehicule_id', $materielId),
                    $transferts->where('materiel_id', $materielId)
                )
            ];
        })->values();

        return response()->json([
            'success' => true,
            'date_journee' => $journee->date,
            'total_materiels' => $data->count(),
            'data' => $data,
        ]);
    } */

    /**
     * RÉCUPÈRE LES PRODUCTIONS DE LA JOURNÉE
     *
     * @param \App\Models\Journee $journee
     * @return \Illuminate\Support\Collection
     */
    protected function getProductionsJournee(Journee $journee)
    {
        return \App\Models\Produit\ProductionMateriel::query()
            ->with(['materiel:id,nom_materiel', 'categorieTravail:id,nom_categorie'])
            ->whereDate('created_at', $journee->date)
            ->get()
            ->map(function ($production) {
                return [
                    'id' => $production->id,
                    'materiel_id' => $production->materiel_id,
                    'heure_debut' => $production->heure_debut,
                    'heure_fin' => $production->heure_fin,
                    'compteur_debut' => $production->compteur_debut,
                    'compteur_fin' => $production->compteur_fin,
                    'gasoil_debut' => $production->gasoil_debut,
                    'gasoil_fin' => $production->gasoil_fin,
                    'consommation_reelle_par_heure' => $production->consommation_reelle_par_heure,
                    'consommation_horaire_reference' => $production->consommation_horaire_reference,
                    'ecart_consommation_horaire' => $production->ecart_consommation_horaire,
                    'statut_consommation_horaire' => $production->statut_consommation_horaire,
                    'consommation_totale' => $production->consommation_totale,
                    'consommation_destination_reference' => $production->consommation_destination_reference,
                    'ecart_consommation_destination' => $production->ecart_consommation_destination,
                    'statut_consommation_destination' => $production->statut_consommation_destination,
                    'observation' => $production->observation,
                    'categorie_travail' => $production->categorieTravail->nom_categorie ?? null,
                ];
            });
    }

    /**
     * RÉCUPÈRE LES LIVRAISONS DE LA JOURNÉE
     *
     * @param \App\Models\Journee $journee
     * @return \Illuminate\Support\Collection
     */
    protected function getLivraisonsJournee(Journee $journee)
    {
        return \App\Models\BL\BonLivraison::query()
            ->with([
                'vehicule:id,nom_materiel',
                'client:id,nom_client',
                'chauffeur:id,nom_conducteur',
                'bonCommandeProduit.article.uniteLivraison'
            ])
            ->whereDate('date_livraison', $journee->date)
            ->get()
            ->map(function ($livraison) {
                return [
                    'id' => $livraison->id,
                    'vehicule_id' => $livraison->vehicule_id,
                    'numBL' => $livraison->numBL,
                    'heure_depart' => $livraison->heure_depart,
                    'heure_arrive' => $livraison->heure_arrive,
                    'gasoil_depart' => $livraison->gasoil_depart,
                    'gasoil_arrive' => $livraison->gasoil_arrive,
                    'compteur_depart' => $livraison->compteur_depart,
                    'compteur_arrive' => $livraison->compteur_arrive,
                    'quantite' => $livraison->quantite,
                    'quantite_deja_livree' => $livraison->quantite_deja_livree,
                    'client' => $livraison->client->nom_client ?? null,
                    'chauffeur' => $livraison->chauffeur->nom ?? null,
                    'isDelivred' => $livraison->isDelivred,
                    'date_livraison' => $livraison->date_livraison,
                    'consommation_totale' => $livraison->consommation_totale,
                    'produit' => $livraison->bonCommandeProduit->article->nom_article ?? null,
                    'unite' => $livraison->bonCommandeProduit->article->uniteLivraison->nom_unite ?? null,
                    'remarque' => $livraison->remarque,
                ];
            });
    }

    /**
     * RÉCUPÈRE LES OPÉRATIONS VÉHICULE DE LA JOURNÉE
     */
    protected function getOperationsVehiculeJournee(Journee $journee)
    {
        return \App\Models\OperationVehicule::query()
            ->with([
                'vehicule:id,nom_materiel',
                'chauffeur:id,nom_conducteur',
                'categoriesTravail:id,nom_categorie'
            ])
            ->whereDate('date_livraison', $journee->date)
            ->get()
            ->map(function ($op) {
                return [
                    'id' => $op->id,
                    'vehicule_id' => $op->vehicule_id,
                    'heure_depart' => optional($op->heure_depart)->format('H:i'),
                    'heure_arrive' => optional($op->heure_arrive)->format('H:i'),
                    'distance_km' => $op->distance_km,
                    'compteur_depart' => $op->compteur_depart,
                    'compteur_arrive' => $op->compteur_arrive,
                    'gasoil_depart' => $op->gasoil_depart,
                    'gasoil_arrive' => $op->gasoil_arrive,
                    'consommation_totale' => $op->consommation_totale,
                    'chauffeur' => $op->chauffeur->nom_conducteur ?? null,
                    'categorie_travail' => $op->categoriesTravail->nom_categorie ?? null,
                    'observation' => $op->observation,
                ];
            });
    }

    /**
     * RÉCUPÈRE LES TRANSFERTS DE LA JOURNÉE
     *
     * @param \App\Models\Journee $journee
     * @return \Illuminate\Support\Collection
     */
    protected function getTransfertsJournee(Journee $journee)
    {
        return \App\Models\Produit\TransfertProduit::query()
            ->with([
                'materiel:id,nom_materiel',
                'lieuStockageDepart:id,nom',
                'lieuStockageArrive:id,nom',
                'produit:id,nom_article',
                'chauffeur:id,nom_conducteur',
                'unite'
            ])
            ->whereDate('date', $journee->date)
            ->get()
            ->map(function ($transfert) {
                return [
                    'id' => $transfert->id,
                    'materiel_id' => $transfert->materiel_id,
                    'date' => $transfert->date,
                    'heure_depart' => $transfert->heure_depart,
                    'heure_arrivee' => $transfert->heure_arrivee,
                    'gasoil_depart' => $transfert->gasoil_depart,
                    'gasoil_arrive' => $transfert->gasoil_arrive,
                    'compteur_depart' => $transfert->compteur_depart,
                    'compteur_arrive' => $transfert->compteur_arrive,
                    'quantite' => $transfert->quantite,
                    'lieu_depart' => $transfert->lieuStockageDepart->nom ?? null,
                    'lieu_arrive' => $transfert->lieuStockageArrive->nom ?? null,
                    'produit' => $transfert->produit->nom_article ?? null,
                    'chauffeur' => $transfert->chauffeur->nom_conducteur ?? null,
                    'isDelivred' => $transfert->isDelivred,
                    'consommation_totale' => $transfert->consommation_totale,
                    'remarque' => $transfert->remarque,
                    'unite' => $transfert->unite->nom_unite ?? null
                ];
            });
    }

    /**
     * CALCULE UN RÉSUMÉ DES OPÉRATIONS POUR UN MATÉRIEL
     *
     * @param \Illuminate\Support\Collection $productions
     * @param \Illuminate\Support\Collection $livraisons
     * @param \Illuminate\Support\Collection $transferts
     * @return array
     */
    protected function calculerResumeOperations(
        $productions,
        $livraisons,
        $transferts,
        $operationsVehicules
    ) {
        $totalProductions = $productions->count();
        $totalLivraisons = $livraisons->count();
        $totalTransferts = $transferts->count();

        // Calcul des heures totales de production
        $heuresProduction = $productions->sum(function ($production) {
            if ($production['heure_debut'] && $production['heure_fin']) {
                $debut = \Carbon\Carbon::parse($production['heure_debut']);
                $fin = \Carbon\Carbon::parse($production['heure_fin']);
                return $debut->diffInHours($fin);
            }
            return 0;
        });

        // Calcul des quantités totales livrées
        $quantiteLivree = $livraisons->sum('quantite');

        // Calcul des quantités totales transférées
        $quantiteTransferee = $transferts->sum('quantite');

        $totalVehicules = $operationsVehicules->count();

        $distanceTotale = $operationsVehicules->sum('distance_km');

        return [
            'total_operations' => $totalProductions + $totalLivraisons + $totalTransferts + $totalVehicules,
            'total_productions' => $totalProductions,
            'total_livraisons' => $totalLivraisons,
            'total_transferts' => $totalTransferts,
            'total_operations_vehicule' => $totalVehicules,
            'heures_production' => $heuresProduction,
            'quantite_livree' => $quantiteLivree,
            'quantite_transferee' => $quantiteTransferee,
            'distance_totale' => $distanceTotale,
        ];
    }

    public function bilanOperationsDuJour()
    {
        $journee = Journee::journeeAujourdhui();

        if (!$journee) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune journée active'
            ], 404);
        }

        return $this->operationsParMateriel($journee);
    }

    /**
     * RÉCUPÈRE LES OPÉRATIONS D'UN MATÉRIEL PENDANT UNE JOURNÉE
     *
     * @param \App\Models\Journee $journee
     * @param int $materielId
     * @return \Illuminate\Http\JsonResponse
     */
    public function operationsMaterielJournee(Journee $journee, $materielId)
    {
        // Validation de la journée
        abort_if(!$journee->isBegin || $journee->isEnd, 403, 'Journée invalide');

        // Vérification du matériel
        $materiel = \App\Models\Parametre\Materiel::findOrFail($materielId);

        // Récupération des opérations pour ce matériel
        $productions = $this->getProductionsJournee($journee)
            ->where('materiel_id', $materielId)
            ->values();

        $livraisons = $this->getLivraisonsJournee($journee)
            ->where('vehicule_id', $materielId)
            ->values();

        $transferts = $this->getTransfertsJournee($journee)
            ->where('materiel_id', $materielId)
            ->values();

        $operationsVehicules = $this->getOperationsVehiculeJournee($journee)
            ->where('vehicule_id', $materielId)
            ->values();

        // Calcul du résumé
        $resume = $this->calculerResumeOperations(
            $productions,
            $livraisons,
            $transferts,
            $operationsVehicules,
            $operationsVehicules
        );

        return response()->json([
            'success' => true,
            'date_journee' => $journee->date,
            'data' => [
                'materiel_id'   => $materiel->id,
                'nom_materiel'  => $materiel->nom_materiel,
                'productions'   => $productions,
                'livraisons'    => $livraisons,
                'transferts'    => $transferts,
                'operationsVehicules' => $operationsVehicules,
                'resume'        => $resume,
            ],
        ]);
    }
}
