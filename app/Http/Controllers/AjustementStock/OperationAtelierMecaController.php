<?php

namespace App\Http\Controllers\AjustementStock;

use App\Http\Controllers\Controller;
use App\Http\Requests\AjustementStock\OperationAtelierMecaRequest;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Stock;
use App\Models\OperationAtelierMeca;
use App\Models\Parametre\Materiel;
use App\Services\GasoilConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationAtelierMecaController extends Controller
{
    public function index(Request $request)
    {
        $query = OperationAtelierMeca::with([
            'materiel:id,nom_materiel,categorie,actuelGasoil',
            'user:id,nom',
            'stock:id,quantite,article_id',
            'remisMateriel:id,nom_materiel,categorie,actuelGasoil',
            'remisUser:id,nom',
        ])->orderByDesc('operation_at')->orderByDesc('id');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('materiel', function ($q) use ($search) {
                $q->where('nom_materiel', 'like', '%' . $search . '%');
            });
        }

        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('operation_at', '>=', $request->date_start);
        }

        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('operation_at', '<=', $request->date_end);
        }

        if ($request->has('etat') && !empty($request->etat)) {
            $etat = $request->etat;

            if (in_array($etat, ['remis', '1', 1, true, 'true'], true)) {
                $query->where('is_remis', true);
            }

            if (in_array($etat, ['en_atelier', '0', 0, false, 'false'], true)) {
                $query->where('is_remis', false);
            }
        }

        $perPage = $request->per_page ?? 15;
        return response()->json($query->paginate($perPage));
    }

    public function store(OperationAtelierMecaRequest $request)
    {
        $data = $request->validated();

        try {
            $result = DB::transaction(function () use ($data) {
                $materiel = Materiel::where('id', $data['materiel_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$materiel) {
                    throw ValidationException::withMessages([
                        'materiel_id' => ['Le matériel sélectionné est introuvable.']
                    ]);
                }

                $articleGasoil = ArticleDepot::whereRaw('LOWER(nom_article) = ?', ['gasoil'])->first();
                if (!$articleGasoil) {
                    throw ValidationException::withMessages([
                        'gasoil_retirer' => ['Article gasoil introuvable dans le dépôt.']
                    ]);
                }

                $capaciteCm = (float) $materiel->capaciteCm;
                if ($capaciteCm <= 0) {
                    throw ValidationException::withMessages([
                        'materiel_id' => ["Le matériel {$materiel->nom_materiel} n'a pas de capacité cm valide."]
                    ]);
                }

                $gasoilAvant = (float) $materiel->actuelGasoil;
                $epsilonCm = 0.01; // tolérance d'arrondi cm/litre

                // Priorité au niveau mesuré après retrait (plus fiable que la reconversion litres -> cm)
                if (array_key_exists('gasoil_apres_retrait_cm', $data) && $data['gasoil_apres_retrait_cm'] !== null) {
                    $gasoilApresRetraitCm = (float) $data['gasoil_apres_retrait_cm'];

                    if ($gasoilApresRetraitCm > $gasoilAvant + $epsilonCm) {
                        throw ValidationException::withMessages([
                            'gasoil_apres_retrait_cm' => [
                                'Le gasoil après retrait ne peut pas être supérieur au gasoil actuel du matériel.'
                            ]
                        ]);
                    }

                    if (abs($gasoilApresRetraitCm) <= $epsilonCm) {
                        $gasoilApresRetraitCm = 0.0;
                    }

                    $quantiteRetireeCm = $gasoilAvant - $gasoilApresRetraitCm;
                    $gasoilRetirer = GasoilConversionService::cmToLiter($quantiteRetireeCm, $capaciteCm);
                } else {
                    $gasoilRetirer = (float) $data['gasoil_retirer'];
                    $quantiteRetireeCm = GasoilConversionService::literToCm($gasoilRetirer, $capaciteCm);
                }

                if ($quantiteRetireeCm <= 0 || $gasoilRetirer <= 0) {
                    throw ValidationException::withMessages([
                        'gasoil_retirer' => [
                            'La quantité retirée est nulle. Vérifiez la valeur saisie.'
                        ]
                    ]);
                }

                if ($quantiteRetireeCm > $gasoilAvant + $epsilonCm) {
                    $disponibleLitres = GasoilConversionService::cmToLiter($gasoilAvant, $capaciteCm);
                    throw ValidationException::withMessages([
                        'gasoil_retirer' => [
                            'Gasoil insuffisant dans le matériel. Disponible: '
                                . round($disponibleLitres, 2) . ' L, demandé: ' . round($gasoilRetirer, 2) . ' L.'
                        ]
                    ]);
                }

                // Absorber les micro-dépassements dus aux arrondis
                if ($quantiteRetireeCm > $gasoilAvant) {
                    $quantiteRetireeCm = $gasoilAvant;
                    $gasoilRetirer = GasoilConversionService::cmToLiter($quantiteRetireeCm, $capaciteCm);
                }

                $stockAtelier = Stock::where('article_id', $articleGasoil->id)
                    ->where('isAtelierMeca', true)
                    ->whereNull('lieu_stockage_id')
                    ->lockForUpdate()
                    ->first();

                if (!$stockAtelier) {
                    $stockAtelier = Stock::create([
                        'article_id' => $articleGasoil->id,
                        'categorie_article_id' => $articleGasoil->categorie_id,
                        'lieu_stockage_id' => null,
                        'quantite' => 0,
                        'isAtelierMeca' => true,
                    ]);
                }

                $stockAtelierAvant = (float) $stockAtelier->quantite;
                $stockAtelierApres = $stockAtelierAvant + $gasoilRetirer;
                $resteGasoil = max(0, $gasoilAvant - $quantiteRetireeCm);

                $materiel->actuelGasoil = $resteGasoil;
                $materiel->save();

                $stockAtelier->quantite = $stockAtelierApres;
                $stockAtelier->isAtelierMeca = true;
                $stockAtelier->save();

                $operation = OperationAtelierMeca::create([
                    'materiel_id' => $materiel->id,
                    'stock_id' => $stockAtelier->id,
                    'gasoil_retirer' => $gasoilRetirer,
                    'quantite_retiree_cm' => $quantiteRetireeCm,
                    'reste_gasoil' => $resteGasoil,
                    'stock_atelier_avant' => $stockAtelierAvant,
                    'stock_atelier_apres' => $stockAtelierApres,
                    'commentaire' => $data['commentaire'] ?? null,
                    'user_id' => auth()->id(),
                    'operation_at' => $data['operation_at'] ?? now(),
                ]);

                $operation->load(['materiel:id,nom_materiel,categorie', 'user:id,nom']);

                return [
                    'operation' => $operation,
                    'stock_atelier' => $stockAtelier,
                    'materiel' => $materiel,
                ];
            });

            return response()->json([
                'message' => 'Opération atelier mécanique enregistrée avec succès.',
                'data' => $result,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => "Erreur lors de l'opération atelier mécanique.",
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function remettre(OperationAtelierMeca $operation, Request $request)
    {
        $validated = $request->validate([
            'materiel_id_cible' => 'required|exists:materiels,id',
        ], [
            'materiel_id_cible.required' => 'Le matériel cible est obligatoire.',
            'materiel_id_cible.exists' => 'Le matériel cible est introuvable.',
        ]);

        try {
            $result = DB::transaction(function () use ($operation, $validated) {
                $operation = OperationAtelierMeca::where('id', $operation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($operation->is_remis) {
                    throw ValidationException::withMessages([
                        'operation' => ['Cette opération a déjà été remise dans un matériel.']
                    ]);
                }

                $materielCible = Materiel::where('id', $validated['materiel_id_cible'])
                    ->lockForUpdate()
                    ->first();

                if (!$materielCible) {
                    throw ValidationException::withMessages([
                        'materiel_id_cible' => ['Matériel cible introuvable.']
                    ]);
                }

                $capaciteCmCible = (float) $materielCible->capaciteCm;
                if ($capaciteCmCible <= 0) {
                    throw ValidationException::withMessages([
                        'materiel_id_cible' => ["Le matériel {$materielCible->nom_materiel} n'a pas de capacité cm valide."]
                    ]);
                }

                $gasoilRetireLitres = (float) $operation->gasoil_retirer;
                if ($gasoilRetireLitres <= 0) {
                    throw ValidationException::withMessages([
                        'operation' => ['La quantité retirée pour cette opération est invalide.']
                    ]);
                }

                $stockAtelier = null;
                if ($operation->stock_id) {
                    $stockAtelier = Stock::where('id', $operation->stock_id)
                        ->lockForUpdate()
                        ->first();
                }

                if (!$stockAtelier) {
                    $stockAtelier = Stock::where('isAtelierMeca', true)
                        ->whereNull('lieu_stockage_id')
                        ->lockForUpdate()
                        ->first();
                }

                if (!$stockAtelier) {
                    throw ValidationException::withMessages([
                        'stock' => ['Stock atelier mécanique introuvable.']
                    ]);
                }

                $epsilon = 0.01;
                $stockAtelierActuel = (float) $stockAtelier->quantite;
                if (($stockAtelierActuel + $epsilon) < $gasoilRetireLitres) {
                    throw ValidationException::withMessages([
                        'stock' => [
                            'Stock atelier insuffisant. Disponible: '
                                . round($stockAtelierActuel, 2) . ' L, demandé: '
                                . round($gasoilRetireLitres, 2) . ' L.'
                        ]
                    ]);
                }

                $stockAtelierApres = $stockAtelierActuel - $gasoilRetireLitres;
                if ($stockAtelierApres < 0 && abs($stockAtelierApres) <= $epsilon) {
                    $stockAtelierApres = 0;
                }

                $quantiteAjouteeCm = GasoilConversionService::literToCm($gasoilRetireLitres, $capaciteCmCible);
                if ($quantiteAjouteeCm <= 0) {
                    throw ValidationException::withMessages([
                        'operation' => ['Impossible de convertir la quantité en cm pour le matériel cible.']
                    ]);
                }

                $materielAvant = (float) $materielCible->actuelGasoil;
                $materielApres = $materielAvant + $quantiteAjouteeCm;

                $materielCible->actuelGasoil = $materielApres;
                $materielCible->save();

                $stockAtelier->quantite = max(0, $stockAtelierApres);
                $stockAtelier->save();

                $operation->is_remis = true;
                $operation->remis_materiel_id = $materielCible->id;
                $operation->remis_user_id = auth()->id();
                $operation->remis_at = now();
                $operation->save();

                $operation->load([
                    'materiel:id,nom_materiel,categorie,actuelGasoil',
                    'user:id,nom',
                    'remisMateriel:id,nom_materiel,categorie,actuelGasoil',
                    'remisUser:id,nom'
                ]);

                return [
                    'operation' => $operation,
                    'stock_atelier_avant' => $stockAtelierActuel,
                    'stock_atelier_apres' => $stockAtelier->quantite,
                    'materiel_cible_avant_cm' => $materielAvant,
                    'materiel_cible_apres_cm' => $materielApres,
                    'gasoil_remis_litres' => $gasoilRetireLitres,
                    'gasoil_ajoute_cm' => $quantiteAjouteeCm,
                ];
            });

            return response()->json([
                'message' => 'Gasoil remis dans le matériel avec succès.',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Erreur lors de la remise du gasoil.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
