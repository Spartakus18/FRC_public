<?php

namespace App\Http\Controllers\Consommable;

use App\Exports\GasoilExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consommable\GasoilRequest;
use App\Http\Requests\Consommable\GasoilTransfertRequest;
use App\Http\Requests\PerteOperationRequest;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\AjustementStock\Sortie;
use App\Models\AjustementStock\Stock;
use App\Models\BC\BonGasoil;
use App\Models\Consommable\Gasoil;
use App\Models\GasoilJournee;
use App\Models\Journee;
use App\Models\Parametre\Materiel;
use App\Models\Parametre\Unite;
use App\Models\PerteGasoilOperation;
use App\Models\User;
use App\Notifications\GasoilSeuilAtteint;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Facades\Excel;
use SebastianBergmann\CodeCoverage\Report\Xml\Unit;
use App\Services\GasoilConversionService;
use Illuminate\Support\Facades\Log;
use Throwable;

class GasoilController extends Controller
{
    /**
     * Liste des gasoils
     */
    public function index(Request $request)
    {
        $query = Gasoil::with(['materielCible', 'materielSource', 'source', 'bon']);

        // Filtre par recherche (nom du matériel)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('materielCible', function ($q) use ($search) {
                $q->where('nom_materiel', 'like', '%' . $search . '%');
            });
        }

        // Filtre par date de début
        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('updated_at', '>=', $request->date_start);
        }

        // Filtre par date de fin
        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('updated_at', '<=', $request->date_end);
        }

        //  Tri par date de création décroissante
        $query->orderBy('updated_at', 'desc');

        // Pagination
        $perPage = $request->per_page ?? 10;
        $gasoils = $query->paginate($perPage);

        // Transformation des données pour ajouter les infos de stock
        /** @var \Illuminate\Pagination\LengthAwarePaginator $gasoils */
        $gasoils->getCollection()->transform(function ($gasoil) {
            // Ajouter le nom du lieu de stockage source
            $gasoil->nom_lieu_stockage_source = $gasoil->source->nom ?? null;

            // Si la source est un lieu de stockage, on calcule avant/après ajout
            if ($gasoil->source_lieu_stockage_id) {
                $articleGasoil = ArticleDepot::where('nom_article', 'gasoil')->first();

                if ($articleGasoil) {
                    $stockActuel = Stock::where('article_id', $articleGasoil->id)
                        ->where('lieu_stockage_id', $gasoil->source_lieu_stockage_id)
                        ->first();

                    if ($stockActuel) {
                        $gasoil->quantite_apres_ajout = (float) $stockActuel->quantite;
                        $gasoil->quantite_avant_ajout = (float) $stockActuel->quantite + (float) $gasoil->quantite;
                    } else {
                        $gasoil->quantite_avant_ajout = null;
                        $gasoil->quantite_apres_ajout = null;
                    }
                }
            } else {
                // Si source n’est pas un lieu de stockage (station)
                $gasoil->quantite_avant_ajout = null;
                $gasoil->quantite_apres_ajout = null;
            }

            return $gasoil;
        });

        /**
         * Restriction pour le prix (caché pour les non-admins)
         */
        $user = auth()->user();
        $isAdmin = $user && $user->role_id === 1;

        if (!$isAdmin) {
            $gasoils->getCollection()->transform(function ($gasoil) {
                return $gasoil->makeHidden(['prix_total', 'prix_gasoil']);
            });
        }

        return response()->json($gasoils);
    }

    /**
     * Confirmer le versement d'un gasoil
     *
     * @param Gasoil $gasoil
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Gasoil $gasoil, PerteOperationRequest $request)
    {
        $data = $request->validated();
        $modificationManuelle = $data['modificationManuelle'] ?? false;

        DB::beginTransaction();

        try {
            if ($gasoil->is_consumed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce gasoil a déjà été versé'
                ], 409);
            }

            // Récupérer le matériel cible
            $materiel = $gasoil->materielCible;

            if (!$materiel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Matériel cible introuvable'
                ], 404);
            }

            // Récupérer les valeurs de modification si présentes
            $actuelGasoilModifie = isset($data['actuelGasoil']) ? (float) $data['actuelGasoil'] : null;
            $gasoilApresAjoutModifie = isset($data['gasoilApresAjout']) ? (float) $data['gasoilApresAjout'] : null;
            $motifModification = $data['motifModification'] ?? null;
            $materielGoAvant = (float) $materiel->actuelGasoil;

            // Conversion litres → cm pour la quantité de gasoil
            $quantiteLitres = (float) $gasoil->quantite;
            $capaciteCm = (float) $materiel->capaciteCm;
            $quantiteCm = GasoilConversionService::literToCm($quantiteLitres, $capaciteCm);

            // Si modification manuelle, ajuster les valeurs
            if ($modificationManuelle && $actuelGasoilModifie !== null && $gasoilApresAjoutModifie !== null) {
                // Calculer la différence entre la valeur calculée et la valeur modifiée
                $gasoilAvant = $materielGoAvant;
                $gasoilApresCalcule = $materielGoAvant + $quantiteCm;

                $differenceAvant = $actuelGasoilModifie - $gasoilAvant;
                $differenceApres = $gasoilApresAjoutModifie - $gasoilApresCalcule;

                // Créer une opération de perte pour tracer la modification
                if ($motifModification) {
                    PerteGasoilOperation::create([
                        'gasoil_avant' => $gasoilAvant,
                        'gasoil_apres' => $gasoilApresAjoutModifie,
                        'gasoil_id' => $gasoil->id,
                        'motif' => $motifModification
                    ]);
                }

                // Mettre à jour le gasoil du matériel avec la valeur modifiée
                $materiel->actuelGasoil = $gasoilApresAjoutModifie;
                $materiel->save();

                // Journaliser la modification
                Log::info('Modification manuelle du gasoil', [
                    'gasoil_id' => $gasoil->id,
                    'materiel_id' => $materiel->id,
                    'materiel_nom' => $materiel->nom_materiel,
                    'gasoil_avant_original' => $gasoilAvant,
                    'gasoil_avant_modifie' => $actuelGasoilModifie,
                    'gasoil_apres_calcule' => $gasoilApresCalcule,
                    'gasoil_apres_modifie' => $gasoilApresAjoutModifie,
                    'difference_avant' => $differenceAvant,
                    'difference_apres' => $differenceApres,
                    'motif' => $motifModification,
                    'user_id' => auth()->id()
                ]);
            } else {
                // Logique normale sans modification
                $materiel->actuelGasoil += $quantiteCm;
                $materiel->save();
            }
            $materielGoApres = (float) $materiel->actuelGasoil;

            // Récupérer l'unité "L" (litre)
            $uniteLitre = Unite::whereIn(
                DB::raw('LOWER(nom_unite)'),
                ['l', 'litre']
            )->first();


            if (!$uniteLitre) {
                throw new \Exception('Unité "L" (litre) non trouvée dans la base de données');
            }

            // Récupérer l'id du gasoil dans l'article depot
            $gasoil_article = ArticleDepot::with('categorie')->whereRaw(
                'LOWER(nom_article) = ?',
                ['gasoil']
            )->first();

            // Mise à jour du stock source (en litres)
            $stock = Stock::where('lieu_stockage_id', $gasoil->source_lieu_stockage_id)
                ->where('article_id', $gasoil_article->id)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                throw new \Exception('Stock source gasoil introuvable pour ce lieu');
            }


            if (!$stock) {
                throw new \Exception('Stock source introuvable');
            }

            // ENREGISTRER LES QUANTITÉS DE STOCK AVANT/APRÈS
            $quantiteStockAvant = (float) $stock->quantite;
            $quantiteStockApres = $quantiteStockAvant - $quantiteLitres;
            // Ajuster la quantité de sortie
            $quantiteSortie = $quantiteLitres;

            // Vérifier que le stock est suffisant
            if ($stock->quantite < $quantiteLitres) {
                throw new \Exception('Stock source insuffisant. Disponible: ' . $stock->quantite . ' L, Demandé: ' . $quantiteLitres . ' L');
            }

            $stock->quantite = $quantiteStockApres;
            $stock->save();

            // --- Création d'une entrée dans la table Sortie ---
            // Récupérer le bon de gasoil pour le numéro de bon
            $bonGasoil = $gasoil->bon;
            if (!$bonGasoil) {
                $bonGasoil = BonGasoil::find($gasoil->bon_id);
            }


            // Créer l'enregistrement de sortie
            $motifSortie = "Versement de gasoil - Bon n° " . ($bonGasoil ? $bonGasoil->num_bon : 'N/A');

            $categorieId = $gasoil_article->categorie_id;

            $sortie = Sortie::create([
                'user_name' => auth()->user()->nom ?? 'System',
                'article_id' => $gasoil_article->id,
                'categorie_article_id' => $categorieId,
                'lieu_stockage_id' => $gasoil->source_lieu_stockage_id,
                'quantite' => $quantiteSortie,
                'unite_id' => $uniteLitre->id,
                'motif' => $motifSortie,
                'sortie' => now()->toDateString(),
            ]);


            $gasoil->update([
                'quantite_stock_avant' => $quantiteStockAvant,
                'quantite_stock_apres' => $quantiteStockApres,
                'materiel_go_avant' => $materielGoAvant,
                'materiel_go_apres' => $materielGoApres,
                'is_consumed' => true,
            ]);

            // Récupérer la journée d'aujourd'hui
            $journee = Journee::journeeAujourdhui();
            if ($journee && !$journee->isEnd) {
                GasoilJournee::markHasGasoil($materiel->id, $journee->id);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Versement confirmé avec succès',
                'data' => [
                    'quantite_litres' => $quantiteSortie,
                    'quantite_cm' => round($quantiteCm, 2),
                    'gasoil_actuel_cm' => round($materiel->actuelGasoil, 2),
                    'sortie_id' => $sortie->id,
                    'bon_numero' => $bonGasoil ? $bonGasoil->num_bon : 'N/A',
                    'modification_manuelle' => $modificationManuelle
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Erreur confirmation versement gasoil', [
                'gasoil_id' => $gasoil->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation du versement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportExcel(Request $request)
    {
        $filters = $request->only(['search', 'date_start', 'date_end']);
        return Excel::download(new GasoilExport($filters), 'gasoil.xlsx');
    }

    /**
     * Ajout de gasoil
     */
    public function store(GasoilRequest $request)
    {
        $data = $request->validated();
        $data['ajouter_par'] = auth()->user()->nom;
        $bonGasoil = BonGasoil::where('id', $data['bon_id'])->firstOrFail();

        // --- 1. VÉRIFICATIONS (Lecture seule) ---
        $materielCible = Materiel::find($data['materiel_id_cible']);
        if (!$materielCible) {
            return response()->json(['message' => 'Matériel cible non trouvé'], 404);
        }

        $nouveauNiveau = $materielCible->actuelGasoil + $data['quantite'];
        if ($nouveauNiveau > $materielCible->capaciteL) {
            return response()->json([
                'message' => 'La quantité dépasse la capacité maximale du matériel. Quantité maximale autorisée: ' . ($materielCible->capacite - $materielCible->actuelGasoil) . ' L'
            ], 422); // 422: Unprocessable Entity
        }

        // --- 2. PRÉPARATION DES DONNÉES ---
        if ($data['source'] === 'station') {
            $data['source_station'] = 'station';
            $data['source_lieu_stockage_id'] = null;
        } else {
            $data['source_lieu_stockage_id'] = $data['source'];
            $data['source_station'] = null;
        }
        // Historiser le niveau du matériel cible avant/après l'ajout
        $data['materiel_go_avant'] = $materielCible->actuelGasoil;
        $data['materiel_go_apres'] = $nouveauNiveau;
        unset($data['source']);

        $gasoil = null; // Initialiser pour l'avoir en dehors du scope de la transaction
        $seuilAtteint = false; // Pour gérer l'événement après le commit

        // --- 3. BLOC DE TRANSACTION (Écritures) ---
        // Toutes les opérations de base de données sont ici.
        try {
            DB::transaction(function () use ($data, $materielCible, $nouveauNiveau, &$gasoil, &$seuilAtteint, $bonGasoil) {

                // Opération 1: Créer l'historique Gasoil
                $gasoil = Gasoil::create($data);

                // Opération 2: Mettre à jour le matériel cible
                $materielCible->actuelGasoil = $nouveauNiveau;
                $materielCible->save();

                // Opération 3: Gérer le stock (si la source n'est pas la station)
                if ($data['source_station'] === null && $data['source_lieu_stockage_id'] !== null) {

                    $articleGasoil = ArticleDepot::with('categorie')->where('nom_article', 'gasoil')->first();
                    if (!$articleGasoil) {
                        // Lever une exception annule la transaction
                        throw new \Exception('Article gasoil non trouvé', 422);
                    }

                    $stock = Stock::where('article_id', $articleGasoil->id)
                        ->where('lieu_stockage_id', $data['source_lieu_stockage_id'])
                        ->first(); // Utiliser first() sans try-catch ici

                    if (!$stock) {
                        throw new \Exception('Stock non trouvé pour ce lieu de stockage', 422);
                    }

                    if ($stock->quantite < $data['quantite']) {
                        throw new \Exception('Stock insuffisant. Stock disponible: ' . $stock->quantite . ' L', 422);
                    }

                    // Mettre à jour le stock
                    $stock->quantite -= $data['quantite'];
                    $stock->save(); // Si cela échoue, une exception est levée -> ROLLBACK

                    $categorieId = $articleGasoil->categorie_id;

                    // Opération 4: Créer l'historique de sortie
                    // (Si 'L' n'est pas trouvé, value() renvoie null,
                    // la création échouera à cause de la contrainte FK -> ROLLBACK)
                    Sortie::create([
                        'user_name' => auth()->user()->nom,
                        'article_id' => $articleGasoil->id,
                        'categorie_article_id' => $categorieId,
                        'lieu_stockage_id' => $data['source_lieu_stockage_id'],
                        'quantite' => $data['quantite'],
                        'unite_id' => Unite::where('nom_unite', 'L')->value('id'),
                        'motif' => "Versement de gasoil - Bon n° " . $bonGasoil->num_bon,
                        'sortie' => now()->toDateString(),
                    ]);
                }

                // Opération 5: Gérer le Bon Gasoil
                // firstOrFail() lèvera une ModelNotFoundException si non trouvé -> ROLLBACK

                if ($bonGasoil->is_consumed) {
                    throw new \Exception("Ce bon gasoil a déjà été utilisé", 409); // 409: Conflit
                }

                $bonGasoil->update(['is_consumed' => true]);

                // Opération 6: Gérer la notification de seuil
                if ($materielCible->actuelGasoil <= $materielCible->seuil && !$materielCible->seuil_notified) {
                    $materielCible->update(['seuil_notified' => true]);
                    $seuilAtteint = true;
                }
            }); // <-- FIN DE LA TRANSACTION 

            // --- 4. OPÉRATIONS POST-TRANSACTION ---
            // N'est exécuté que si la transaction a réussi
            if ($seuilAtteint) {
                // Il est préférable de lancer les événements *après* le commit,
                // pour que les listeners voient les données validées.
                event(new GasoilSeuilAtteint($materielCible));
            }

            // Charger la relation pour la réponse
            $gasoil->load(['materielCible']);
            return response()->json([
                'message' => 'Gasoil ajouté avec succès',
                'data' => $gasoil,
            ]);
        } catch (ModelNotFoundException $e) {
            // Catch spécifique pour le bon non trouvé
            return response()->json([
                'message' => 'Bon gasoil non trouvé'
            ], 404);
        } catch (Throwable $e) {
            // Catch générique pour TOUTES les autres erreurs
            // (Stock insuffisant, Article non trouvé, Erreur DB, etc.)

            // Récupérer le code HTTP que nous avons défini (422, 409)
            $code = $e->getCode();

            // S'assurer que le code est un code d'erreur HTTP valide
            if (!is_int($code) || $code < 400 || $code >= 600) {
                $code = 500; // 500: Erreur serveur interne par défaut
            }

            return response()->json([
                'message' => $e->getMessage() // Renvoie le message d'erreur (ex: "Stock insuffisant...")
            ], $code);
        }
    }

    /**
     * Transfert de gasoil entre matériels
     */
    public function transfert(GasoilTransfertRequest $request)
    {
        $data = $request->validated();
        $data['ajouter_par'] = auth()->user()->nom;
        $data['source'] = 'Interne';
        $data['type_operation'] = 'transfert';

        // Récupérer la modification manuelle si présente
        $modificationManuelle = $data['modificationManuelle'] ?? false;
        $actuelGasoilModifie = isset($data['actuelGasoil']) ? (float) $data['actuelGasoil'] : null;
        $gasoilApresAjoutModifie = isset($data['gasoilApresAjout']) ? (float) $data['gasoilApresAjout'] : null;
        $motifModification = $data['motifModification'] ?? null;

        DB::beginTransaction();

        try {
            $user = auth()->user();
            // Récupération des matériels
            $materielSource = Materiel::find($data['materiel_id_source']);
            $materielCible = Materiel::find($data['materiel_id_cible']);

            if (!$materielSource || !$materielCible) {
                return response()->json([
                    'success' => false,
                    'message' => 'Matériel source ou cible introuvable'
                ], 404);
            }

            // --- 1. CRÉATION DU BON DE GASOIL ---
            $bonGasoil = BonGasoil::create([
                'num_bon' => $data['num_bon'],
                'quantite' => $data['quantite'],
                'source_lieu_stockage_id' => $data['source'] === 'lieu_stockage' ? $data['source_lieu_stockage_id'] : null,
                'ajouter_par' => $user->nom,
            ]);

            $data['bon_id'] = $bonGasoil->id;
            $data['is_consumed'] = true;

            // Conversion litres → cm pour les deux matériels
            $quantiteLitres = (float) $data['quantite'];
            $capaciteCmSource = (float) $materielSource->capaciteCm;
            $capaciteCmCible = (float) $materielCible->capaciteCm;

            $quantiteCmSource = GasoilConversionService::literToCm($quantiteLitres, $capaciteCmSource);
            $quantiteCmCible = GasoilConversionService::literToCm($quantiteLitres, $capaciteCmCible);

            // Gestion de la modification manuelle pour le matériel source
            if ($modificationManuelle && $actuelGasoilModifie !== null && $motifModification) {
                // Calculer la différence pour le matériel source
                $gasoilAvantSource = $materielSource->actuelGasoil;
                $gasoilApresCalculeSource = $gasoilAvantSource - $quantiteCmSource;

                // Créer une opération de perte pour tracer la modification
                PerteGasoilOperation::create([
                    'gasoil_avant' => $gasoilAvantSource,
                    'gasoil_apres' => $actuelGasoilModifie,
                    'gasoil_id' => null, // Pas encore créé
                    'motif' => $motifModification . " (Matériel source: {$materielSource->nom_materiel})"
                ]);

                // Utiliser la valeur modifiée
                $materielSource->actuelGasoil = $actuelGasoilModifie;

                Log::info('Modification manuelle du gasoil source', [
                    'materiel_source_id' => $materielSource->id,
                    'materiel_source_nom' => $materielSource->nom_materiel,
                    'gasoil_avant_original' => $gasoilAvantSource,
                    'gasoil_avant_modifie' => $actuelGasoilModifie,
                    'quantite_retiree_cm' => $quantiteCmSource,
                    'motif' => $motifModification,
                    'user_id' => auth()->id()
                ]);
            } else {
                // Logique normale pour le matériel source
                // Vérifier que le matériel source a suffisamment de gasoil
                if ($materielSource->actuelGasoil < $quantiteCmSource) {
                    throw new \Exception('Stock insuffisant dans le matériel source');
                }

                $materielSource->actuelGasoil -= $quantiteCmSource;
            }

            Log::info("Debug modif lors du transfert", [
                'modificationManuelle' => $modificationManuelle,
                'gasoilApresAjoutModifie' => $gasoilApresAjoutModifie,
                'motifModification' => $motifModification
            ]);

            // Gestion de la modification manuelle pour le matériel cible
            if ($modificationManuelle && $gasoilApresAjoutModifie !== null && $motifModification) {
                // Calculer la différence pour le matériel cible
                $gasoilAvantCible = $materielCible->actuelGasoil;
                $gasoilApresCalculeCible = $gasoilAvantCible + $quantiteCmCible;

                // Créer une opération de perte pour tracer la modification
                PerteGasoilOperation::create([
                    'gasoil_avant' => $gasoilAvantCible,
                    'gasoil_apres' => $gasoilApresAjoutModifie,
                    'gasoil_id' => null, // Pas encore créé
                    'motif' => $motifModification . " (Matériel cible: {$materielCible->nom_materiel})"
                ]);

                // Utiliser la valeur modifiée
                $materielCible->actuelGasoil = $gasoilApresAjoutModifie;

                Log::info('Modification manuelle du gasoil cible', [
                    'materiel_cible_id' => $materielCible->id,
                    'materiel_cible_nom' => $materielCible->nom_materiel,
                    'gasoil_avant_original' => $gasoilAvantCible,
                    'gasoil_apres_modifie' => $gasoilApresAjoutModifie,
                    'quantite_ajoutee_cm' => $quantiteCmCible,
                    'motif' => $motifModification,
                    'user_id' => auth()->id()
                ]);
            } else {
                // Logique normale pour le matériel cible
                $materielCible->actuelGasoil += $quantiteCmCible;
            }

            // Sauvegarder les modifications des matériels
            $materielSource->save();
            $materielCible->save();

            // Créer l'enregistrement de gasoil
            $gasoil = Gasoil::create($data);

            // Mettre à jour les IDs des opérations de perte avec l'ID du gasoil
            if ($modificationManuelle && $motifModification) {
                PerteGasoilOperation::whereNull('gasoil_id')
                    ->where('motif', 'LIKE', "%{$motifModification}%")
                    ->update(['gasoil_id' => $gasoil->id]);
            }

            // Vérifier les seuils pour les deux matériels
            $seuilSourceAtteint = $materielSource->actuelGasoil <= $materielSource->seuil;
            $seuilCibleAtteint = $materielCible->actuelGasoil <= $materielCible->seuil;

            $admins = \App\Models\User::where('role_id', '1')->get();

            if ($seuilSourceAtteint) {
                Notification::send($admins, new GasoilSeuilAtteint($materielSource));
            }

            if ($seuilCibleAtteint) {
                Notification::send($admins, new GasoilSeuilAtteint($materielCible));
            }

            // Charger les relations
            $gasoil->load(['materielSource', 'materielCible']);

            // MARQUER HAS_GASOIL DANS GASOILJOURNEE
            // Récupérer la journée d'aujourd'hui
            $journee = Journee::journeeAujourdhui();
            if ($journee && !$journee->isEnd) {
                // Marquer pour le matériel source (qui a donné du gasoil)
                GasoilJournee::markHasGasoil($materielSource->id, $journee->id);

                // Marquer pour le matériel cible (qui a reçu du gasoil)
                GasoilJournee::markHasGasoil($materielCible->id, $journee->id);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfert effectué avec succès',
                'data' => [
                    'gasoil' => $gasoil,
                    'quantite_litres' => $quantiteLitres,
                    'quantite_cm_source' => round($quantiteCmSource, 2),
                    'quantite_cm_cible' => round($quantiteCmCible, 2),
                    'gasoil_actuel_source' => round($materielSource->actuelGasoil, 2),
                    'gasoil_actuel_cible' => round($materielCible->actuelGasoil, 2),
                    'modification_manuelle' => $modificationManuelle
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Erreur lors du transfert de gasoil', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du transfert de gasoil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mise à jour de gasoil
     */
    public function update_old(GasoilRequest $request, Gasoil $gasoil)
    {
        $data = $request->validated();
        $data['modifier_par'] = auth()->user()->nom;

        // Vérification de la capacité du matériel pour la mise à jour
        $materielCible = Materiel::find($data['materiel_id_cible']);

        if (!$materielCible) {
            return response()->json(['message' => 'Matériel cible non trouvé'], 404);
        }

        // Calcul du nouveau niveau en tenant compte de l'ancienne quantité
        $ancienneQuantite = $gasoil->quantite;
        $nouveauNiveau = $materielCible->actuelGasoil - $ancienneQuantite + $data['quantite'];

        // Vérifier si la nouvelle quantité dépasse la capacité
        if ($nouveauNiveau > $materielCible->capaciteL) {
            return response()->json([
                'message' => 'La quantité dépasse la capacité maximale du matériel. Quantité maximale autorisée: ' . ($materielCible->capacite - ($materielCible->actuelGasoil - $ancienneQuantite)) . ' L'
            ], 422);
        }

        // Transformation de la source pour la mise à jour
        if ($data['source'] === 'station') {
            $data['source_station'] = 'station';
            $data['source_lieu_stockage_id'] = null;
        } else {
            $data['source_lieu_stockage_id'] = $data['source'];
            $data['source_station'] = null;
        }

        unset($data['source']);

        // Gestion du stock pour la mise à jour
        if ($gasoil->source_station === null && $gasoil->source_lieu_stockage_id !== null) {
            // Restaurer l'ancien stock
            $articleGasoil = ArticleDepot::where('nom_article', 'gasoil')->first();
            if ($articleGasoil) {
                $ancienStock = Stock::where('article_id', $articleGasoil->id)
                    ->where('lieu_stockage_id', $gasoil->source_lieu_stockage_id)
                    ->first();
                if ($ancienStock) {
                    $ancienStock->quantite += $ancienneQuantite;
                    $ancienStock->save();
                }
            }
        }

        // Mettre à jour le gasoil
        $gasoil->update($data);

        // Mettre à jour le niveau du matériel
        $materielCible->actuelGasoil = $nouveauNiveau;
        $materielCible->save();

        // Gestion du nouveau stock
        if ($data['source_station'] === null && $data['source_lieu_stockage_id'] !== null) {
            $articleGasoil = ArticleDepot::where('nom_article', 'gasoil')->first();
            if ($articleGasoil) {
                $nouveauStock = Stock::where('article_id', $articleGasoil->id)
                    ->where('lieu_stockage_id', $data['source_lieu_stockage_id'])
                    ->first();
                if ($nouveauStock) {
                    if ($nouveauStock->quantite < $data['quantite']) {
                        // Annuler les modifications en cas de stock insuffisant
                        $materielCible->actuelGasoil = $materielCible->actuelGasoil + $ancienneQuantite - $data['quantite'];
                        $materielCible->save();

                        // Restaurer l'ancien gasoil
                        $gasoil->update([
                            'quantite' => $ancienneQuantite,
                            'source_station' => $gasoil->source_station,
                            'source_lieu_stockage_id' => $gasoil->source_lieu_stockage_id
                        ]);

                        return response()->json([
                            'message' => 'Stock insuffisant. Stock disponible: ' . $nouveauStock->quantite . ' L'
                        ], 422);
                    }
                    $nouveauStock->quantite -= $data['quantite'];
                    $nouveauStock->save();
                }
            }
        }

        $gasoil->load(['materielCible']);
        return response()->json([
            'message' => 'Gasoil modifié avec succès',
            'data' => $gasoil
        ]);
    }

    /**
     * Mise à jour d'une opération de gasoil (VERSION AMÉLIORÉE)
     * Cette méthode remplace ou améliore la méthode update existante
     */
    public function updateGasoil(Request $request, $id)
    {
        // Validation des données
        $validated = $request->validate([
            'type_operation' => 'sometimes|required|string|in:versement,transfert,achat,approStock',
            'quantite' => 'sometimes|required|numeric|min:0.01',
            'materiel_id_cible' => 'sometimes|required|exists:materiels,id',
            'materiel_id_source' => 'nullable|exists:materiels,id',
            'prix_gasoil' => 'nullable|numeric|min:0',
            'source' => 'sometimes|required', // peut être 'station' ou un ID de lieu_stockage
        ]);

        DB::beginTransaction();

        try {
            // Récupérer l'opération de gasoil
            $gasoil = Gasoil::with(['materielCible', 'materielSource'])->findOrFail($id);

            // Récupérer les anciennes valeurs
            $ancienneQuantite = $gasoil->quantite;
            $ancienMaterielCibleId = $gasoil->materiel_id_cible;
            $ancienneSource = $gasoil->source_station ? 'station' : $gasoil->source_lieu_stockage_id;

            // Préparer les nouvelles valeurs
            $nouvelleQuantite = $validated['quantite'] ?? $ancienneQuantite;
            $nouveauMaterielCibleId = $validated['materiel_id_cible'] ?? $ancienMaterielCibleId;

            // Gérer la source
            $nouvelleSourceStation = null;
            $nouvelleSourceLieuId = null;

            if (isset($validated['source'])) {
                if ($validated['source'] === 'station') {
                    $nouvelleSourceStation = 'station';
                } else {
                    $nouvelleSourceLieuId = $validated['source'];
                }
            } else {
                $nouvelleSourceStation = $gasoil->source_station;
                $nouvelleSourceLieuId = $gasoil->source_lieu_stockage_id;
            }

            // Vérification de la capacité du matériel cible
            /* $materielCible = Materiel::findOrFail($nouveauMaterielCibleId); */

            // Vérification du stock si la source est un lieu de stockage
            /* if ($nouvelleSourceLieuId) {
                $articleGasoil = ArticleDepot::whereRaw('LOWER(nom_article) = ?', ['gasoil'])->first();

                if (!$articleGasoil) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Article gasoil non trouvé dans la base de données'
                    ], 500);
                }

                // Calculer la différence de quantité si on change juste la quantité pour le même lieu
                if ($nouvelleSourceLieuId == $ancienneSource) {
                    $difference = $nouvelleQuantite - $ancienneQuantite;

                    if ($difference > 0) {
                        // On augmente la quantité, vérifier le stock
                        $stockDisponible = Stock::where('lieu_stockage_id', $nouvelleSourceLieuId)
                            ->where('article_id', $articleGasoil->id)
                            ->sum('quantite');

                        if ($difference > $stockDisponible) {
                            DB::rollBack();
                            return response()->json([
                                'message' => 'Erreur de validation des stocks',
                                'error' => "Quantité insuffisante. Augmentation demandée: {$difference} L, Disponible: {$stockDisponible} L"
                            ], 422);
                        }
                    }
                } else {
                    // On change de lieu de stockage, vérifier le stock du nouveau lieu
                    $stockDisponible = Stock::where('lieu_stockage_id', $nouvelleSourceLieuId)
                        ->where('article_id', $articleGasoil->id)
                        ->sum('quantite');

                    if ($nouvelleQuantite > $stockDisponible) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Erreur de validation des stocks',
                            'error' => "Quantité insuffisante dans le nouveau lieu de stockage. Demande: {$nouvelleQuantite} L, Disponible: {$stockDisponible} L"
                        ], 422);
                    }
                }
            } */

            // Préparer les données de mise à jour
            $dataToUpdate = [
                'modifier_par' => auth()->user()->nom ?? 'system',
            ];

            if (isset($validated['type_operation'])) {
                $dataToUpdate['type_operation'] = $validated['type_operation'];
            }

            if (isset($validated['quantite'])) {
                $dataToUpdate['quantite'] = $validated['quantite'];
            }

            if (isset($validated['materiel_id_cible'])) {
                $dataToUpdate['materiel_id_cible'] = $validated['materiel_id_cible'];
            }

            if (isset($validated['materiel_id_source'])) {
                $dataToUpdate['materiel_id_source'] = $validated['materiel_id_source'];
            }

            if (isset($validated['prix_gasoil'])) {
                $dataToUpdate['prix_gasoil'] = $validated['prix_gasoil'];
            }

            // Mise à jour de la source
            if (isset($validated['source'])) {
                $dataToUpdate['source_station'] = $nouvelleSourceStation;
                $dataToUpdate['source_lieu_stockage_id'] = $nouvelleSourceLieuId;
            }

            // Mettre à jour l'opération
            $gasoil->update($dataToUpdate);

            // Recharger les relations
            $gasoil->load([
                'materielCible',
                'materielSource',
                'source',
                'bon'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Opération de gasoil modifiée avec succès',
                'data' => $gasoil
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Erreur lors de la modification de l\'opération de gasoil', [
                'gasoil_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Erreur lors de la modification de l\'opération de gasoil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour la quantité d'une opération de gasoil non consommée
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateQuantite(Request $request, $id)
    {
        try {
            // Trouver l'opération de gasoil
            $gasoil = Gasoil::findOrFail($id);

            // Vérifier que l'opération peut être modifiée
            // Éditable si : non consommée OU (consommée mais sans source_lieu_stockage_id)
            if ($gasoil->is_consumed && $gasoil->source_lieu_stockage_id) {
                return response()->json([
                    'message' => 'Impossible de modifier une opération consommée avec un stock source.'
                ], 400);
            }

            // Valider les données
            $validated = $request->validate([
                'quantite' => 'required|numeric|min:0.01'
            ]);

            // Sauvegarder l'ancienne quantité pour le log
            $oldQuantite = $gasoil->quantite;

            // Mettre à jour la quantité
            $gasoil->quantite = $validated['quantite'];

            // Mettre à jour modifier_par si vous avez un système d'authentification
            if (auth()->check()) {
                $gasoil->modifier_par = auth()->user()->name ?? auth()->user()->email;
            }

            // Recalculer quantite_stock_apres si applicable
            if ($gasoil->source_lieu_stockage_id && $gasoil->quantite_stock_avant !== null) {
                $difference = $validated['quantite'] - $oldQuantite;
                $gasoil->quantite_stock_apres = $gasoil->quantite_stock_avant - $validated['quantite'];

                // Mettre à jour le stock du lieu de stockage
                $lieuStockage = Lieu_stockage::find($gasoil->source_lieu_stockage_id);
                if ($lieuStockage) {
                    $lieuStockage->quantite_stock -= $difference;
                    $lieuStockage->save();
                }
            }

            $gasoil->save();

            // Log de l'opération (optionnel)
            Log::info("Quantité de gasoil modifiée", [
                'gasoil_id' => $id,
                'ancienne_quantite' => $oldQuantite,
                'nouvelle_quantite' => $validated['quantite'],
                'utilisateur' => auth()->user()->name ?? 'inconnu'
            ]);

            return response()->json([
                'message' => 'Quantité mise à jour avec succès',
                'data' => $gasoil->fresh() // Recharger les données
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Données invalides',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour de la quantité de gasoil", [
                'gasoil_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de la mise à jour'
            ], 500);
        }
    }



    public function getGasoilSummary(Request $request)
    {
        $period = $request->input('period', '7days');

        $query = Materiel::query();

        if ($period === '7days') {
            $query->where('updated_at', '>=', now()->subDays(7));
        } elseif ($period === 'last_week') {
            $query->whereBetween('updated_at', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek()
            ]);
        } elseif ($period === 'this_month') {
            $query->whereBetween('updated_at', [
                now()->startOfMonth(),
                now()->endOfMonth()
            ]);
        }

        $total = $query->sum('actuelGasoil');

        return response()->json([
            'total_gasoil' => $total,
        ]);
    }

    public function getGasoilBymateriel(Request $request)
    {
        $period = $request->input('period', '7days');

        $query = Materiel::query();

        if ($period === '7days') {
            $query->where('updated_at', '>=', now()->subDays(7));
        } elseif ($period === 'last_week') {
            $query->whereBetween('updated_at', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek()
            ]);
        } elseif ($period === 'this_month') {
            $query->whereBetween('updated_at', [
                now()->startOfMonth(),
                now()->endOfMonth()
            ]);
        }

        $data = $query
            ->selectRaw('nom_materiel, SUM(actuelGasoil) as total')
            ->groupBy('nom_materiel')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->nom_materiel ?? 'Inconnu',
                    'value' => (float) $item->total,
                ];
            });

        return response()->json($data);
    }

    /**
     * Supprimer un gasoil non consommé
     */
    public function destroy(Gasoil $gasoil)
    {
        DB::beginTransaction();

        try {
            // Vérifier si le gasoil est déjà consommé
            if ($gasoil->is_consumed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer un gasoil déjà versé.'
                ], 403);
            }

            // Récupérer les informations nécessaires pour la restauration
            $materielCible = $gasoil->materielCible;
            $bonGasoil = $gasoil->bon;

            // 2. Supprimer les opérations de perte associées (si elles existent)
            if ($gasoil->perteOperation) {
                $gasoil->perteOperation->delete();
            }

            // 4. Vérifier si c'est le dernier gasoil du bon
            if ($bonGasoil) {
                $autresGasoilsDuBon = Gasoil::where('bon_id', $bonGasoil->id)
                    ->where('id', '!=', $gasoil->id)
                    ->count();

                // Si c'est le dernier gasoil du bon, supprimer le bon aussi
                if ($autresGasoilsDuBon === 0) {
                    $bonGasoil->delete();
                }
            }

            // 5. Soft delete du gasoil
            $gasoil->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Opération de gasoil supprimée avec succès.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur lors de la suppression du gasoil', [
                'gasoil_id' => $gasoil->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du gasoil.',
                'error' => $e->getMessage(),
                'trace' => $e->getTrace()
            ], 500);
        }
    }
}
