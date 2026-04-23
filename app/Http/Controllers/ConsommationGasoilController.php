<?php

namespace App\Http\Controllers;

use App\Models\BL\BonLivraison;
use App\Models\Consommable\Gasoil;
use App\Models\ConsommationGasoil;
use App\Models\Parametre\Materiel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConsommationGasoilController extends Controller
{
    public function index(Request $request)
    {
        $periode = $request->get('periode', '7days');

        // 🔹 Déterminer les bornes de la période actuelle et précédente
        switch ($periode) {
            case '7days':
                $startCurrent = now()->subDays(7);
                $endCurrent = now();
                $startPrev = now()->subDays(14);
                $endPrev = now()->subDays(7);
                break;

            case 'last_week':
                $startCurrent = now()->subWeek()->startOfWeek();
                $endCurrent = now()->subWeek()->endOfWeek();
                $startPrev = now()->subWeeks(2)->startOfWeek();
                $endPrev = now()->subWeeks(2)->endOfWeek();
                break;

            case 'this_month':
                $startCurrent = now()->startOfMonth();
                $endCurrent = now()->endOfMonth();
                $startPrev = now()->subMonth()->startOfMonth();
                $endPrev = now()->subMonth()->endOfMonth();
                break;

            default:
                // fallback sur 7 derniers jours
                $startCurrent = now()->subDays(7);
                $endCurrent = now();
                $startPrev = now()->subDays(14);
                $endPrev = now()->subDays(7);
        }

        // 🔸 Données de la période actuelle
        $current = ConsommationGasoil::with('vehicule')
            ->whereBetween('date_consommation', [$startCurrent, $endCurrent])
            ->get();

        // 🔸 Données de la période précédente
        $previous = ConsommationGasoil::with('vehicule')
            ->whereBetween('date_consommation', [$startPrev, $endPrev])
            ->get();

        // 🔸 Regrouper par véhicule
        $groupedCurrent = $current->groupBy('vehicule_id');
        $groupedPrevious = $previous->groupBy('vehicule_id');

        $data = $groupedCurrent->map(function ($items, $vehiculeId) use ($groupedPrevious) {
            $vehicule = $items->first()->vehicule;
            $totalCurrent = $items->sum('quantite');
            $totalDistance = $items->sum('distance_km');

            $prevTotal = $groupedPrevious->has($vehiculeId)
                ? $groupedPrevious[$vehiculeId]->sum('quantite')
                : 0;

            // 🔹 Calcul de variation %
            $variation = $prevTotal > 0
                ? (($totalCurrent - $prevTotal) / $prevTotal) * 100
                : null;

            return [
                'vehicule_id' => $vehiculeId,
                'vehicule_nom' => $vehicule->nom_materiel ?? 'N/A',
                'total_consommation' => round($totalCurrent, 2),
                'total_distance' => round($totalDistance, 2),
                'consommation_precedente' => round($prevTotal, 2),
                'variation_pourcent' => $variation !== null ? round($variation, 2) : null,
            ];
        })->values();

        // 🔸 Consommation totale globale (utile pour ton KPI)
        $globalCurrent = $current->sum('quantite');
        $globalPrev = $previous->sum('quantite');
        $globalVariation = $globalPrev > 0
            ? (($globalCurrent - $globalPrev) / $globalPrev) * 100
            : null;

        return response()->json([
            'periode' => $periode,
            'debut_periode' => $startCurrent->toDateString(),
            'fin_periode' => $endCurrent->toDateString(),
            'global' => [
                'total_consommation' => round($globalCurrent, 2),
                'total_precedente' => round($globalPrev, 2),
                'variation_pourcent' => $globalVariation !== null ? round($globalVariation, 2) : null,
            ],
            'data' => $data,
        ]);
    }

    // GET /consommations/by-machine?periode=7days
    public function byMachine(Request $request)
    {
        $period = $request->input('periode', '7days');

        $query = ConsommationGasoil::query()->with('vehicule');

        // 🔹 Appliquer les filtres de période
        if ($period === '7days') {
            $query->where('date_consommation', '>=', now()->subDays(7));
        } elseif ($period === 'last_week') {
            $query->whereBetween('date_consommation', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek(),
            ]);
        } elseif ($period === 'this_month') {
            $query->whereBetween('date_consommation', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]);
        }

        // 🔹 Groupement par véhicule
        $data = $query->get()
            ->groupBy('vehicule_id')
            ->map(function ($items, $vehiculeId) {
                $vehicule = $items->first()->vehicule;
                return [
                    'name' => $vehicule->nom_materiel ?? 'N/A',
                    'value' => $items->sum('quantite'),
                ];
            })
            ->values();

        return response()->json($data);
    }

    /**
     * Récupère tous les matériels avec les champs de base
     */
    public function materiel()
    {
        $materiels = Materiel::select([
            'id',
            'nom_materiel',
            'status',
            'seuil',
            'actuelGasoil',
            'categorie',
            'consommation_horaire'
        ])
            ->orderBy('nom_materiel', 'asc')
            ->get();

        return response()->json($materiels);
    }


    public function materielConsommation(Request $request)
    {
        // Récupérer les paramètres de filtrage de la requête
        $dateDebut = $request->input('date_debut');
        $dateFin = $request->input('date_fin');
        $materielId = $request->input('materiel_id'); // Optionnel pour filtrer par matériel spécifique

        // Construire la requête de base pour charger les matériels
        $query = Materiel::query();

        // Si un ID de matériel est spécifié, filtrer par cet ID
        if ($materielId) {
            $query->where('id', $materielId);
        }

        // Charger les matériels avec leurs consommations et les relations associées
        $materiels = $query->with(['consommationGasoils' => function ($query) use ($dateDebut, $dateFin) {
            // Si des dates sont fournies, filtrer les consommations par période
            if ($dateDebut && $dateFin) {
                $query->whereBetween('date_consommation', [$dateDebut, $dateFin]);
            }

            // Charger les relations pour les consommations filtrées
            $query->with([
                'bonLivraison' => function ($q) {
                    $q->with(['chauffeur', 'aideChauffeur']);
                },
                'transfertProduit',
                'productionMateriel' => function ($q) {
                    $q->with([
                        'production' => function ($q2) {
                            $q2->with(['produits' => function ($q3) {
                                $q3->with('articleDepot');
                            }]);
                        },
                        'categorieTravail'
                    ]);
                },
                'destination',
                'operationVehicule' => function ($q) {
                    $q->with(['chauffeur', 'aideChauffeur', 'categoriesTravail']);
                }
            ]);
        }])->get();

        // Formater les résultats
        $result = $materiels->map(function ($materiel) {
            return [
                'id' => $materiel->id,
                'nom_materiel' => $materiel->nom_materiel,
                'reference_consommation' => $materiel->consommation_horaire,
                'categorie' => $materiel->categorie,
                'consommations' => $materiel->consommationGasoils->map(function ($consommation) {
                    // Récupérer les articles produits pour cette consommation (si elle est liée à une production)
                    $articlesProduits = collect();

                    if (
                        $consommation->productionMateriel &&
                        $consommation->productionMateriel->production &&
                        $consommation->productionMateriel->production->produits
                    ) {
                        $articlesProduits = $consommation->productionMateriel->production->produits->map(function ($productionProduit) {
                            return [
                                'article_id' => optional($productionProduit->articleDepot)->id,
                                'article_nom' => optional($productionProduit->articleDepot)->nom_article,
                                'quantite_produite' => $productionProduit->quantite,
                                'unite_production' => optional($productionProduit->uniteProduction)->nom_unite,
                                'lieu_stockage' => optional($productionProduit->lieuStockage)->nom_lieu,
                            ];
                        });
                    }

                    return [
                        'id' => $consommation->id,
                        'quantite' => $consommation->quantite,
                        'date_consommation' => $consommation->date_consommation,
                        'bon_livraison_id' => $consommation->bon_livraison_id,
                        'bon_livraison' => $consommation->bonLivraison,
                        'transfert_produit_id' => $consommation->transfert_produit_id,
                        'transfert_produit' => $consommation->transfertProduit,
                        'production_materiel_id' => $consommation->production_materiel_id,
                        'production_materiel' => $consommation->productionMateriel,
                        'categorie_travail' => $consommation->productionMateriel ? $consommation->productionMateriel->categorieTravail : null,
                        'destination_id' => $consommation->destination_id,
                        'destination' => $consommation->destination,
                        'articles_produits' => $articlesProduits,
                        'operation_vehicule' => $consommation->operationVehicule ? $consommation->operationVehicule : null
                    ];
                })
            ];
        });

        return response()->json($result);
    }

    /**
     * Récupère les données de consommation d'un véhicule pour un graphique
     * avec filtrage par période
     */
    public function consommationGraphique(Request $request, $vehiculeId)
    {
        // Validation des dates
        $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
        ]);

        $dateDebut = Carbon::parse($request->input('date_debut'));
        $dateFin = Carbon::parse($request->input('date_fin'));

        // Récupérer les consommations du véhicule sur la période avec les relations
        $consommations = ConsommationGasoil::with([
            'bonLivraison',
            'transfertProduit',
            'productionMateriel',
            'productionMateriel.production.produits.articleDepot',
            'productionMateriel.production.produits.uniteProduction'
        ])
            ->where('vehicule_id', $vehiculeId)
            ->whereBetween('date_consommation', [$dateDebut, $dateFin])
            ->orderBy('date_consommation')
            ->get();

        // Formater les données pour le graphique
        $data = [];
        $consommationTotale = 0;
        $consommationMax = 0;
        $consommationMin = PHP_FLOAT_MAX;
        $datesAvecPics = [];

        // Statistiques par type d'activité
        $statsParType = [
            'production' => ['total' => 0, 'operations' => 0],
            'transfert' => ['total' => 0, 'operations' => 0, 'distance_totale' => 0],
            'livraison' => ['total' => 0, 'operations' => 0, 'distance_totale' => 0],
        ];

        // Grouper par date pour le graphique
        $groupedByDate = $consommations->groupBy(function ($item) {
            return Carbon::parse($item->date_consommation)->format('Y-m-d');
        });

        foreach ($groupedByDate as $date => $items) {
            $quantiteDuJour = $items->sum('quantite');
            $consommationTotale += $quantiteDuJour;

            // Calculer la quantité produite pour ce jour
            $quantiteProduiteDuJour = 0;
            $detailsProduction = [];
            $chauffeursDuJour = [];

            foreach ($items as $item) {
                if (
                    $item->production_materiel_id && $item->productionMateriel &&
                    $item->productionMateriel->production && $item->productionMateriel->production->produits
                ) {

                    foreach ($item->productionMateriel->production->produits as $produit) {
                        $quantiteProduiteDuJour += $produit->quantite;

                        // Stocker les détails des produits
                        $detailsProduction[] = [
                            'article_nom' => optional($produit->articleDepot)->nom_article,
                            'quantite' => $produit->quantite,
                            'unite' => optional($produit->uniteProduction)->nom_unite,
                        ];
                    }
                }
                if ($item->bon_livraison_id && $item->bonLivraison && $item->bonLivraison->chauffeur) {
                    $chauffeursDuJour[] = $item->bonLivraison->chauffeur->nom_conducteur;
                }
            }

            // Suivre max/min pour détection de pics
            if ($quantiteDuJour > $consommationMax) {
                $consommationMax = $quantiteDuJour;
            }
            if ($quantiteDuJour < $consommationMin) {
                $consommationMin = $quantiteDuJour;
            }

            // Calculer les métriques par type pour cette journée
            $productionJour = $items->whereNotNull('production_materiel_id');
            $transfertJour = $items->whereNotNull('transfert_produit_id');
            $livraisonJour = $items->whereNotNull('bon_livraison_id');

            // Mettre à jour les statistiques par type
            $statsParType['production']['total'] += $productionJour->sum('quantite');
            $statsParType['production']['operations'] += $productionJour->count();

            $statsParType['transfert']['total'] += $transfertJour->sum('quantite');
            $statsParType['transfert']['operations'] += $transfertJour->count();
            $statsParType['transfert']['distance_totale'] += $transfertJour->sum('distance_km');

            $statsParType['livraison']['total'] += $livraisonJour->sum('quantite');
            $statsParType['livraison']['operations'] += $livraisonJour->count();
            $statsParType['livraison']['distance_totale'] += $livraisonJour->sum('distance_km');

            $chauffeursDuJour = array_unique($chauffeursDuJour);

            $data[] = [
                'date' => $date,
                'consommation' => round($quantiteDuJour, 2),
                'distance_totale' => round($items->sum('distance_km'), 2),
                'nombre_operations' => $items->count(),
                'chauffeurs' => array_values($chauffeursDuJour),
                'details_par_type' => [
                    'production' => [
                        'consommation' => round($productionJour->sum('quantite'), 2),
                        'operations' => $productionJour->count(),
                        'quantite_produite' => $quantiteProduiteDuJour,
                        'details_produits' => $detailsProduction,
                    ],
                    'transfert' => [
                        'consommation' => round($transfertJour->sum('quantite'), 2),
                        'operations' => $transfertJour->count(),
                        'distance' => round($transfertJour->sum('distance_km'), 2),
                    ],
                    'livraison' => [
                        'consommation' => round($livraisonJour->sum('quantite'), 2),
                        'operations' => $livraisonJour->count(),
                        'distance' => round($livraisonJour->sum('distance_km'), 2),
                    ],
                ],
            ];
        }

        // Détection de pics (consommation > moyenne + 30%)
        if (count($data) > 0) {
            $moyenne = $consommationTotale / count($data);
            $seuilPic = $moyenne * 1.3; // 30% au-dessus de la moyenne

            foreach ($data as $point) {
                if ($point['consommation'] > $seuilPic) {
                    $datesAvecPics[] = [
                        'date' => $point['date'],
                        'consommation' => $point['consommation'],
                        'moyenne' => round($moyenne, 2),
                        'ecart_pourcentage' => round(($point['consommation'] - $moyenne) / $moyenne * 100, 2),
                        'details_jour' => $point['details_par_type'],
                    ];
                }
            }
        }

        // Calculer les consommations moyennes par opération pour chaque type
        $statsParType['production']['moyenne_par_operation'] = $statsParType['production']['operations'] > 0
            ? round($statsParType['production']['total'] / $statsParType['production']['operations'], 2)
            : 0;

        /* $statsParType['production']['quantite_totale_produite'] = $quantiteTotaleProduite; */

        $statsParType['transfert']['moyenne_par_operation'] = $statsParType['transfert']['operations'] > 0
            ? round($statsParType['transfert']['total'] / $statsParType['transfert']['operations'], 2)
            : 0;

        $statsParType['transfert']['moyenne_par_km'] = $statsParType['transfert']['distance_totale'] > 0
            ? round($statsParType['transfert']['total'] / $statsParType['transfert']['distance_totale'], 2)
            : 0;

        $statsParType['livraison']['moyenne_par_operation'] = $statsParType['livraison']['operations'] > 0
            ? round($statsParType['livraison']['total'] / $statsParType['livraison']['operations'], 2)
            : 0;

        $statsParType['livraison']['moyenne_par_km'] = $statsParType['livraison']['distance_totale'] > 0
            ? round($statsParType['livraison']['total'] / $statsParType['livraison']['distance_totale'], 2)
            : 0;

        return response()->json([
            'vehicule_id' => $vehiculeId,
            'periode' => [
                'debut' => $dateDebut->format('Y-m-d'),
                'fin' => $dateFin->format('Y-m-d'),
                'jours' => $dateDebut->diffInDays($dateFin) + 1,
            ],
            'statistiques_globales' => [
                'total_consommation' => round($consommationTotale, 2),
                'consommation_moyenne_par_jour' => count($data) > 0 ? round($consommationTotale / count($data), 2) : 0,
                'consommation_max' => $consommationMax,
                'consommation_min' => $consommationMin === PHP_FLOAT_MAX ? 0 : $consommationMin,
                'nombre_jours_avec_donnees' => count($data),
                'nombre_operations_total' => $consommations->count(),
                'distance_totale' => round($consommations->sum('distance_km'), 2),
            ],
            'statistiques_par_type' => $statsParType,
            'pics_detection' => [
                'seuil' => isset($seuilPic) ? round($seuilPic, 2) : 0,
                'dates_avec_pics' => $datesAvecPics,
                'nombre_pics' => count($datesAvecPics),
            ],
            'donnees_graphique' => $data,
            'donnees_detaillees' => $consommations->map(function ($consommation) {
                // Déterminer le type d'activité
                $type = 'non_affecte';
                $typeDetails = null;

                if ($consommation->bon_livraison_id) {
                    $type = 'livraison';
                    $typeDetails = $consommation->bonLivraison;
                } elseif ($consommation->transfert_produit_id) {
                    $type = 'transfert';
                    $typeDetails = $consommation->transfertProduit;
                } elseif ($consommation->production_materiel_id) {
                    $type = 'production';
                    $typeDetails = $consommation->productionMateriel;
                }

                return [
                    'id' => $consommation->id,
                    'date' => $consommation->date_consommation,
                    'quantite' => $consommation->quantite,
                    'distance_km' => $consommation->distance_km,
                    'type_activite' => $type,
                    'type_details' => $typeDetails,
                    'bon_livraison_id' => $consommation->bon_livraison_id,
                    'destination_id' => $consommation->destination_id,
                    'production_materiel_id' => $consommation->production_materiel_id,
                    'transfert_produit_id' => $consommation->transfert_produit_id,
                    // Données de consommation
                    'consommation_reelle_par_heure' => $consommation->consommation_reelle_par_heure,
                    'consommation_horaire_reference' => $consommation->consommation_horaire_reference,
                    'ecart_consommation_horaire' => $consommation->ecart_consommation_horaire,
                    'statut_consommation_horaire' => $consommation->statut_consommation_horaire,
                ];
            }),
        ]);
    }

    /**
     * Récupère le rapport des gasoils pour une période donnée
     */
    public function rapport(Request $request)
    {
        // Validation des dates
        $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
        ]);

        // Convertir en objets Carbon avec gestion du temps
        $dateDebut = Carbon::parse($request->input('date_debut'))->startOfDay();
        $dateFin = Carbon::parse($request->input('date_fin'))->endOfDay();

        // 🔹 Calcul des totaux

        // 1. Total gasoil consommé (depuis ConsommationGasoil)
        $totalConsomme = ConsommationGasoil::whereBetween('date_consommation', [$dateDebut, $dateFin])
            ->sum('quantite');

        // 2. Total gasoil acheté (versement) - depuis Gasoil
        $totalAchete = Gasoil::where('type_operation', 'versement')
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->sum('quantite');

        // 3. Total gasoil transféré (transfert) - depuis Gasoil
        $totalTransfere = Gasoil::where('type_operation', 'transfert')
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->sum('quantite');

        // 🔹 Récupération de l'historique détaillé
        $historique = Gasoil::with([
            'materielSource',
            'materielCible',
            'source',
            'bon' => function ($query) {
                $query->select(['id', 'num_bon']);
            },
            /* 'bon.fournisseur' => function ($query) {
                $query->select(['id', 'nom_fournisseur']);
            } */
        ])
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($gasoil) {
                // Déterminer la source
                $source = null;
                if ($gasoil->type_operation === 'versement') {
                    $source = $gasoil->source_station ?: ($gasoil->source ? $gasoil->source->nom_lieu : null);
                } elseif ($gasoil->type_operation === 'transfert') {
                    $source = $gasoil->materielSource ? $gasoil->materielSource->nom_materiel : null;
                }

                // Déterminer la destination
                $destination = $gasoil->materielCible ? $gasoil->materielCible->nom_materiel : null;

                return [
                    'id' => $gasoil->id,
                    'type_operation' => $gasoil->type_operation,
                    'quantite' => $gasoil->quantite,
                    'prix_gasoil' => $gasoil->prix_gasoil,
                    'prix_total' => $gasoil->prix_total,
                    'date_operation' => $gasoil->created_at->format('Y-m-d H:i:s'),
                    'source' => $source,
                    'destination' => $destination,
                    'bon_numero' => $gasoil->bon ? $gasoil->bon->num_bon : null,
                    'fournisseur' => $gasoil->bon && $gasoil->bon->fournisseur ?
                        $gasoil->bon->fournisseur->nom_fournisseur : null,
                    'ajoute_par' => $gasoil->ajouter_par,
                ];
            });

        // 🔹 Récupération de l'historique des consommations pour la même période
        $historiqueConsommation = ConsommationGasoil::with([
            'vehicule',
            'destination',
            'bonLivraison',
            'transfertProduit',
            'productionMateriel'
        ])
            ->whereBetween('date_consommation', [$dateDebut, $dateFin])
            ->orderBy('date_consommation', 'desc')
            ->get()
            ->map(function ($consommation) {
                // Déterminer le type d'activité
                $typeActivite = 'non_affecte';
                if ($consommation->bon_livraison_id) {
                    $typeActivite = 'livraison';
                } elseif ($consommation->transfert_produit_id) {
                    $typeActivite = 'transfert';
                } elseif ($consommation->production_materiel_id) {
                    $typeActivite = 'production';
                }

                return [
                    'id' => $consommation->id,
                    'date_consommation' => $consommation->date_consommation,
                    'quantite' => $consommation->quantite,
                    'distance_km' => $consommation->distance_km,
                    'vehicule' => $consommation->vehicule ? $consommation->vehicule->nom_materiel : null,
                    'type_activite' => $typeActivite,
                    'destination' => $consommation->destination ? $consommation->destination->nom_destination : null,
                    'bon_livraison_numero' => $consommation->bonLivraison ? $consommation->bonLivraison->numBL : null,
                ];
            });

        // 🔹 Calcul du stock final théorique
        // (stock initial + achats + transferts entrants - consommations - transferts sortants)
        // Note: Cette partie nécessite d'avoir un stock initial de référence

        $stockInitial = 0; // À remplacer par votre logique de stock initial
        $transfertsEntrants = Gasoil::where('type_operation', 'transfert')
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->sum('quantite');

        // Pour le calcul précis, il faudrait distinguer les transferts entrants et sortants
        // Dans cet exemple, on considère tous les transferts comme entrants (simplifié)

        $stockFinalTheorique = $stockInitial + $totalAchete + $transfertsEntrants - $totalConsomme;

        return response()->json([
            'periode' => [
                'debut' => $dateDebut,
                'fin' => $dateFin,
            ],
            'totaux' => [
                'consommation' => round($totalConsomme, 2),
                'achat' => round($totalAchete, 2),
                'transfert' => round($totalTransfere, 2),
                'stock_final_theorique' => round($stockFinalTheorique, 2),
            ],
            'historique_operations' => [
                'achats_et_transferts' => $historique,
                'consommations' => $historiqueConsommation,
            ],
            'statistiques' => [
                'nombre_operations' => $historique->count(),
                'nombre_consommations' => $historiqueConsommation->count(),
                'consommation_moyenne' => $historiqueConsommation->count() > 0 ?
                    round($totalConsomme / $historiqueConsommation->count(), 2) : 0,
            ],
        ]);
    }
}
