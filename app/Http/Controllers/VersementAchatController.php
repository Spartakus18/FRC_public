<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\VersementGasoilAchatRequest;
use App\Http\Requests\VersementHuileAchatRequest;
use App\Models\BC\BonGasoil;
use App\Models\Consommable\Gasoil;
use App\Models\Parametre\Materiel;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Stock;
use App\Models\AjustementStock\Sortie;
use App\Models\BC\BonHuile;
use App\Models\Consommable\Huile;
use App\Models\GasoilJournee;
use App\Models\Journee;
use App\Models\Parametre\Unite;
use App\Models\PerteGasoilOperation;
use App\Notifications\GasoilSeuilAtteint;
use App\Services\GasoilConversionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;
use Illuminate\Support\Facades\Log;

class VersementAchatController extends Controller
{
    /**
     * Ajout d'un bon de gasoil avec son versement (pour station ou lieu de stockage)
     */
    public function storeGasoil(VersementGasoilAchatRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();
            $user = auth()->user();

            // --- 1. CRÉATION DU BON DE GASOIL ---
            $bonGasoil = BonGasoil::create([
                'num_bon' => $data['num_bon'],
                'quantite' => $data['quantite'],
                'source_lieu_stockage_id' => $data['source'] === 'lieu_stockage' ? $data['source_lieu_stockage_id'] : null,
                'ajouter_par' => $user->nom,
            ]);

            // --- 2. PRÉPARATION DES DONNÉES POUR LE GASOIL ---
            $gasoilData = [
                'bon_id' => $bonGasoil->id,
                'quantite' => $data['quantite'],
                'prix_gasoil' => $data['prix_gasoil'] ?? null,
                'type_operation' => 'versement',
                'materiel_id_cible' => $data['materiel_id_cible'],
                'ajouter_par' => $user->nom,
                'is_consumed' => false,
            ];

            // Gestion de la source
            if ($data['source'] === 'station') {
                $gasoilData['source_station'] = 'station';
                $gasoilData['source_lieu_stockage_id'] = null;
            } else {
                $gasoilData['source_station'] = null;
                $gasoilData['source_lieu_stockage_id'] = $data['source_lieu_stockage_id'];
            }

            // --- 3. VÉRIFICATION DU MATÉRIEL CIBLE ---
            $materielCible = Materiel::find($data['materiel_id_cible']);
            if (!$materielCible) {
                throw new ModelNotFoundException('Matériel cible non trouvé');
            }

            // --- 5. GESTION DU NIVEAU DE GASOIL DU MATÉRIEL ---
            $materielGoAvant = (float) $materielCible->actuelGasoil;
            $materielGoApres = (float) $materielCible->actuelGasoil;

            if (isset($data['modificationManuelle']) && $data['modificationManuelle']) {
                // Modification manuelle - l'utilisateur spécifie les valeurs
                $gasoil_avant = (float) $data['actuelGasoil'];
                $gasoil_apres = (float) $data['gasoilApresAjout'];

                $materielGoAvant = $gasoil_avant;
                $materielGoApres = $gasoil_apres;

                // Mise à jour du niveau
                $materielCible->actuelGasoil = $gasoil_apres;
                $materielCible->save();

                // Enregistrement de la perte/ajustement
                PerteGasoilOperation::create([
                    'gasoil_avant' => $gasoil_avant,
                    'gasoil_apres' => $gasoil_apres,
                    'gasoil_id' => null, // On mettra à jour après création du gasoil
                    'motif' => $data['motifModification'] ?? 'Modification manuelle'
                ]);
            } else {
                // Mode normal - calcul automatique avec convertion
                $quantiteLitres = (float) $data['quantite'];
                $capaciteCm = (float) $materielCible->capaciteCm;

                $quantiteCm = GasoilConversionService::literToCm(
                    $quantiteLitres,
                    $capaciteCm
                );

                $materielGoApres = $materielGoAvant + $quantiteCm;
                $materielCible->actuelGasoil = $materielGoApres;
                $materielCible->save();
            }

            // Sauvegarder le niveau matériel avant/après pour traçabilité
            $gasoilData['materiel_go_avant'] = $materielGoAvant;
            $gasoilData['materiel_go_apres'] = $materielGoApres;

            // --- 6. CRÉATION DU GASOIL ---
            $gasoil = Gasoil::create($gasoilData);

            // Mise à jour de l'ID du gasoil dans l'enregistrement de perte (si existe)
            if (isset($data['modificationManuelle']) && $data['modificationManuelle']) {
                $perteOperation = PerteGasoilOperation::whereNull('gasoil_id')->latest()->first();
                if ($perteOperation) {
                    $perteOperation->update(['gasoil_id' => $gasoil->id]);
                }
            }

            // --- 7. GESTION DES SEUILS ---
            if ($materielCible->actuelGasoil <= $materielCible->seuil && !$materielCible->seuil_notified) {
                $materielCible->update(['seuil_notified' => true]);
                $admins = \App\Models\User::where('role_id', '1')->get();
                Notification::send($admins, new GasoilSeuilAtteint($materielCible));
            }

            // --- 8. MARQUER LE GASOIL COMME CONSOMMÉ ---
            $gasoil->update(['is_consumed' => true]);

            // --- 9. CHARGEMENT DES RELATIONS POUR LA RÉPONSE ---
            $gasoil->load(['materielCible', 'bon']);
            $bonGasoil->load(['gasoil']);

            // --- 10. MARQUER HAS_GASOIL DANS GASOILJOURNEE ---
            // Récupérer la journée d'aujourd'hui
            $journee = Journee::journeeAujourdhui();
            if ($journee && !$journee->isEnd) {
                GasoilJournee::markHasGasoil($materielCible->id, $journee->id);
            }

            DB::commit();

            return response()->json([
                'message' => 'Versement et bon de gasoil créés avec succès',
                'data' => [
                    'bon' => $bonGasoil,
                    'gasoil' => $gasoil,
                    'materiel' => $materielCible->only(['id', 'nom_materiel', 'actuelGasoil', 'capaciteL']),
                    'modification_manuelle' => $data['modificationManuelle'] ?? false
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors du versement achat', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'user_id' => auth()->id()
            ]);

            $code = $e->getCode();
            if (!is_int($code) || $code < 400 || $code >= 600) {
                $code = 500;
            }

            return response()->json(['message' => $e->getMessage()], $code);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Erreur fatale lors du versement achat', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['message' => 'Erreur interne du serveur'], 500);
        }
    }

    /**
     * Récupérer les opérations de perte pour un gasoil
     */
    public function getPerteOperations($gasoilId)
    {
        try {
            $operations = PerteGasoilOperation::where('gasoil_id', $gasoilId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['data' => $operations]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la récupération des opérations'], 500);
        }
    }

    /**
     * Ajout d'un bon d'huile avec son versement
     */
    public function storeHuile(VersementHuileAchatRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();
            $user = auth()->user();

            // --- 2. CRÉATION DU BON D'HUILE ---
            $bonHuile = BonHuile::create([
                'num_bon' => $data['num_bon'],
                'ajouter_par' => $user->nom,
            ]);

            // --- 3. PRÉPARATION DES DONNÉES POUR L'HUILE ---
            $huileData = [
                'bon_id' => $bonHuile->id,
                'quantite' => $data['quantite'],
                'prix_total' => $data['prix_total'] ?? null,
                'type_operation' => 'versement',
                'materiel_id_cible' => $data['materiel_id_cible'],
                'subdivision_id_cible' => $data['subdivision_id_cible'] ?? null,
                'article_versement_id' => $data['article_versement_id'],
                'ajouter_par' => $user->nom,
                'is_consumed' => false,
            ];

            // Gestion de la source
            if ($data['source'] === 'station') {
                $huileData['source_station'] = 'station';
                $huileData['source_lieu_stockage_id'] = null;
            } else {
                $huileData['source_station'] = null;
                $huileData['source_lieu_stockage_id'] = $data['source_lieu_stockage_id'];
            }

            // --- 4. VÉRIFICATION DU MATÉRIEL CIBLE ---
            $materielCible = Materiel::find($data['materiel_id_cible']);
            if (!$materielCible) {
                throw new ModelNotFoundException('Matériel cible non trouvé');
            }

            // --- 6. CRÉATION DE L'HUILE ---
            $huile = Huile::create($huileData);

            // --- 7. MARQUER L'HUILE COMME CONSOMMÉE ---
            $huile->update(['is_consumed' => true]);

            // --- 8. CHARGEMENT DES RELATIONS POUR LA RÉPONSE ---
            $huile->load(['materielCible', 'bon', 'articleDepot']);
            $bonHuile->load(['huile']);

            DB::commit();

            return response()->json([
                'message' => 'Versement et bon d\'huile créés avec succès',
                'data' => [
                    'bon' => $bonHuile,
                    'huile' => $huile,
                    'materiel' => $materielCible->only(['id', 'nom_materiel', 'actuelHuile']),
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors du versement huile', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'user_id' => auth()->id()
            ]);

            $code = $e->getCode();
            if (!is_int($code) || $code < 400 || $code >= 600) {
                $code = 500;
            }

            return response()->json(['message' => $e->getMessage()], $code);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Erreur fatale lors du versement huile', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['message' => 'Erreur interne du serveur'], 500);
        }
    }

    /**
     * Récupérer les articles d'huile disponibles pour le versement
     */
    public function getArticlesHuile()
    {
        try {
            // Utilisez le bon modèle selon votre structure
            // Si vous avez un modèle ArticleDepot
            $articles = \App\Models\AjustementStock\ArticleDepot::all();

            return response()->json(['data' => $articles]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la récupération des articles'], 500);
        }
    }
}
