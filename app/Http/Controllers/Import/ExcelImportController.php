<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Models\Parametre\Materiel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Services\ProductionService;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExcelImportController extends Controller
{
    private const ALLOWED_IMPORT_TYPES = [
        'materiel',
        'atelier_materiel',
        'gasoil',
        'huile',
        'pneu',
        'fourniture',
        'production',
        'bon_commande',
        'bon_livraison',
        'bon_commande_livraison',
        'melange',
        'bon_transfert',
        'transfert',
        'vente',
        'entrer',
        'sortie',
        'stock',
    ];

    private const AUTO_VALIDATE_IMPORT_TYPES = [
        'bon_livraison',
        'transfert',
    ];

    private const ALLOWED_FLOW_TYPES = [
        'standard',
        'bon',
        'stock',
    ];

    public function templates(): JsonResponse
    {
        return response()->json([
            'menus' => $this->menuTemplates(),
            'auto_validate_supported' => self::AUTO_VALIDATE_IMPORT_TYPES,
            'gasoil_huile_flow_types' => ['bon', 'stock'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'import_type' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_IMPORT_TYPES)],
            'flow_type'   => ['nullable', 'string', 'in:' . implode(',', self::ALLOWED_FLOW_TYPES)],
            'auto_validate' => ['nullable', 'boolean'],
            'file'        => ['required', 'file', 'mimes:xls,xlsx,csv', 'max:10240'],
        ]);

        $importType   = $validated['import_type'];
        $flowType     = $validated['flow_type'] ?? 'standard';
        $autoValidate = (bool)($validated['auto_validate'] ?? false);

        $this->validateBusinessRules($importType, $flowType, $autoValidate);

        $file      = $validated['file'];
        $timestamp = Carbon::now()->format('Ymd_His');
        $extension = $file->getClientOriginalExtension();
        $safeName  = sprintf('%s_%s_%s.%s', $importType, $flowType, $timestamp, $extension);
        $relativePath = $file->storeAs("imports/excel/{$importType}/{$flowType}", $safeName, 'local');

        try {
            [$rows, $sheetNames] = $this->readSheets($file);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($relativePath);
            return response()->json([
                'message' => "Fichier invalide pour l'import. Utilisez un vrai fichier .xlsx/.xls ou .csv (non renommé).",
                'details' => "Le format du fichier ne peut pas être lu par le moteur d'import.",
            ], 422);
        }

        if (empty($rows)) {
            Storage::disk('local')->delete($relativePath);
            return response()->json(['message' => 'Le fichier Excel est vide ou ne contient aucune feuille exploitable.'], 422);
        }

        // Construire un tableau [{name, data}] pour chaque feuille
        $sheets = [];
        foreach ($rows as $index => $sheetData) {
            $sheets[] = ['name' => $sheetNames[$index] ?? null, 'data' => $sheetData];
        }

        // ── CAS PRODUCTION : fichier avec les 3 feuilles production ──────────────
        $sheetNamesList = array_map(fn($s) => $this->normalizeHeadingKey($s['name'] ?? ''), $sheets);

        $isProductionImport = in_array('produits', $sheetNamesList)
            && in_array('production_produits', $sheetNamesList)
            && in_array('production_materiels', $sheetNamesList);

        if ($isProductionImport) {
            $importResult = $this->importProduction($sheets, $flowType);
            $created      = (int) ($importResult['created'] ?? 0);
            $errorCount   = count($importResult['errors'] ?? []);
            $httpStatus   = ($created === 0 && $errorCount > 0) ? 422 : 200;
            return response()->json([
                'message'       => $created > 0
                    ? "Import production terminé : {$created} production(s) créée(s)" . ($errorCount > 0 ? ", {$errorCount} erreur(s)." : '.')
                    : "Aucune production importée. Vérifiez les données et les colonnes.",
                'import_type'   => 'production',
                'flow_type'     => $flowType,
                'stored_file'   => $relativePath,
                'sheet_count'   => count($sheets),
                'import_result' => $importResult,
            ], $httpStatus);
        }

        // ── CAS TRANSFERT : fichier avec les 2 feuilles ──────────────
        $isTransfertImport = in_array('bon_transferts', $sheetNamesList)
            && in_array('transfert_produits', $sheetNamesList);

        if ($isTransfertImport) {
            $importResult = $this->importTransfert($sheets, $flowType);
            $btCreated = (int) ($importResult['bon_transfert_created'] ?? 0);
            $tpCreated = (int) ($importResult['transfert_produit_created'] ?? 0);
            $errorCount = count($importResult['errors'] ?? []);
            $httpStatus = (($btCreated === 0 && $tpCreated === 0) && $errorCount > 0) ? 422 : 200;
            return response()->json([
                'message'       => $btCreated > 0 || $tpCreated > 0
                    ? "Import transfert terminé : {$btCreated} bon(s) de transfert + {$tpCreated} produit(s) de transfert créé(s)" . ($errorCount > 0 ? ", {$errorCount} erreur(s)." : '.')
                    : "Aucun transfert importé. Vérifiez les données et les colonnes.",
                'import_type'   => 'transfert',
                'flow_type'     => $flowType,
                'stored_file'   => $relativePath,
                'sheet_count'   => count($sheets),
                'import_result' => $importResult,
            ], $httpStatus);
        }

        // ── CAS PNEUS + HISTORIQUE : fichier avec les 2 feuilles ──────────────
        $isPneuImport = in_array('pneus', $sheetNamesList) && in_array('historique_pneus', $sheetNamesList);

        if ($isPneuImport) {
            $importResult = $this->importPneuCombiné($sheets, $flowType);
            $pneuCreated = (int) ($importResult['pneu_created'] ?? 0);
            $histCreated = (int) ($importResult['historique_created'] ?? 0);
            $errorCount = count($importResult['errors'] ?? []);
            $httpStatus = (($pneuCreated === 0 && $histCreated === 0) && $errorCount > 0) ? 422 : 200;
            return response()->json([
                'message'       => $pneuCreated > 0 || $histCreated > 0
                    ? "Import pneus terminé : {$pneuCreated} pneu(s) + {$histCreated} historique(s) créé(s)" . ($errorCount > 0 ? ", {$errorCount} erreur(s)." : '.')
                    : "Aucun pneu importé. Vérifiez les données et les colonnes.",
                'import_type'   => 'pneu',
                'flow_type'     => $flowType,
                'stored_file'   => $relativePath,
                'sheet_count'   => count($sheets),
                'import_result' => $importResult,
            ], $httpStatus);
        }

        // ── CAS FOURNITURES + HISTORIQUE : fichier avec les 2 feuilles ──────────────
        $isFournitureImport = in_array('fournitures', $sheetNamesList) && in_array('historique_fournitures', $sheetNamesList);

        if ($isFournitureImport) {
            $importResult = $this->importFournitureCombine($sheets, $flowType);
            $fournitureCreated = (int) ($importResult['fourniture_created'] ?? 0);
            $histCreated = (int) ($importResult['historique_created'] ?? 0);
            $errorCount = count($importResult['errors'] ?? []);
            $httpStatus = (($fournitureCreated === 0 && $histCreated === 0) && $errorCount > 0) ? 422 : 200;
            return response()->json([
                'message'       => $fournitureCreated > 0 || $histCreated > 0
                    ? "Import fournitures terminé : {$fournitureCreated} fourniture(s) + {$histCreated} historique(s) créé(s)" . ($errorCount > 0 ? ", {$errorCount} erreur(s)." : '.')
                    : "Aucune fourniture importée. Vérifiez les données et les colonnes.",
                'import_type'   => 'fourniture',
                'flow_type'     => $flowType,
                'stored_file'   => $relativePath,
                'sheet_count'   => count($sheets),
                'import_result' => $importResult,
            ], $httpStatus);
        }

        // ── CAS COMBINÉ BC + BL (3 FEUILLES) - DOIT ÊTRE EN PREMIER ──────────────
        $isCombinedBcBlImport = in_array('bon_commandes', $sheetNamesList)
            && in_array('bon_commande_produits', $sheetNamesList)
            && in_array('bon_livraisons', $sheetNamesList);

        if ($isCombinedBcBlImport) {
            $importResult = $this->importBonCommandeEtLivraison($sheets, $flowType);
            $bcCreated    = (int) ($importResult['bon_commande_created'] ?? 0);
            $blCreated    = (int) ($importResult['bon_livraison_created'] ?? 0);
            $errorCount   = count($importResult['errors'] ?? []);
            $httpStatus   = (($bcCreated === 0 && $blCreated === 0) && $errorCount > 0) ? 422 : 200;
            return response()->json([
                'message'       => $bcCreated > 0 || $blCreated > 0
                    ? "Import combiné terminé : {$bcCreated} BC + {$blCreated} BL créé(s)" . ($errorCount > 0 ? ", {$errorCount} erreur(s)." : '.')
                    : "Aucun BC/BL importé. Vérifiez les données et les colonnes.",
                'import_type'   => 'bon_commande_livraison',
                'flow_type'     => $flowType,
                'stored_file'   => $relativePath,
                'sheet_count'   => count($sheets),
                'import_result' => $importResult,
            ], $httpStatus);
        }

        // ── CAS BON DE COMMANDE : fichier avec les 2 feuilles ──────────────
        $isBonCommandeImport = in_array('bon_commandes', $sheetNamesList)
            && in_array('bon_commande_produits', $sheetNamesList);

        if ($isBonCommandeImport) {
            $importResult = $this->importBonCommande($sheets, $flowType, $autoValidate);
            $created      = (int) ($importResult['created'] ?? 0);
            $errorCount   = count($importResult['errors'] ?? []);
            $httpStatus   = ($created === 0 && $errorCount > 0) ? 422 : 200;
            return response()->json([
                'message'       => $created > 0
                    ? "Import Bon de commande terminé : {$created} bon(s) de commande créée(s)" . ($errorCount > 0 ? ", {$errorCount} erreur(s)." : '.')
                    : "Aucun Bon de commande importé. Vérifiez les données et les colonnes.",
                'import_type'   => 'bon_commande',
                'flow_type'     => $flowType,
                'stored_file'   => $relativePath,
                'sheet_count'   => count($sheets),
                'import_result' => $importResult,
            ], $httpStatus);
        }

        // ── CAS GASOIL : fichier avec les 2 feuilles ──────────────
        $isGasoilImport = in_array('bon_gasoils', $sheetNamesList)
            && in_array('gasoils', $sheetNamesList);

        if ($isGasoilImport) {
            $importResult = $this->importGasoil($sheets, $flowType, $autoValidate);
            $created      = (int) ($importResult['created'] ?? 0);
            $errorCount   = count($importResult['errors'] ?? []);
            $httpStatus   = ($created === 0 && $errorCount > 0) ? 422 : 200;
            return response()->json([
                'message'       => $created > 0
                    ? "Import Gasoil terminé : {$created} bon(s) créé(s)" . ($errorCount > 0 ? ", {$errorCount} erreur(s)." : '.')
                    : "Aucun versement gasoil importé. Vérifiez les données et les colonnes.",
                'import_type'   => 'gasoil',
                'flow_type'     => $flowType,
                'stored_file'   => $relativePath,
                'sheet_count'   => count($sheets),
                'import_result' => $importResult,
            ], $httpStatus);
        }

        // ── CAS HUILE : fichier avec les 2 feuilles ──────────────
        $isHuileImport = in_array('bon_huiles', $sheetNamesList)
            && in_array('huiles', $sheetNamesList);

        if ($isHuileImport) {
            $importResult = $this->importHuile($sheets, $flowType, $autoValidate);
            $created      = (int) ($importResult['created'] ?? 0);
            $errorCount   = count($importResult['errors'] ?? []);
            $httpStatus   = ($created === 0 && $errorCount > 0) ? 422 : 200;
            return response()->json([
                'message'       => $created > 0
                    ? "Import Huile terminé : {$created} bon(s) créé(s)" . ($errorCount > 0 ? ", {$errorCount} erreur(s)." : '.')
                    : "Aucun versement huile importé. Vérifiez les données et les colonnes.",
                'import_type'   => 'huile',
                'flow_type'     => $flowType,
                'stored_file'   => $relativePath,
                'sheet_count'   => count($sheets),
                'import_result' => $importResult,
            ], $httpStatus);
        }

        // ── CAS MONO-FEUILLE ──────────────────────────────────────────────────────
        if (count($sheets) === 1) {
            $sheet           = $sheets[0];
            $sheetImportType = $this->resolveSheetImportType($sheet['name'], $importType);
            if ($sheetImportType === null) {
                Storage::disk('local')->delete($relativePath);
                return response()->json([
                    'message'     => "La feuille [{$sheet['name']}] ne correspond à aucun type d'import connu.",
                    'known_types' => self::ALLOWED_IMPORT_TYPES,
                ], 422);
            }

            [$headings, $dataRows] = $this->normalizeSheet($sheet['data']);
            $importResult = $this->dispatchImport($sheetImportType, $headings, $dataRows, $flowType);
            $created    = (int) ($importResult['created'] ?? 0);
            $updated    = (int) ($importResult['updated'] ?? 0);
            $errorCount = count($importResult['errors'] ?? []);

            if ($created === 0 && $updated === 0 && $errorCount > 0) {
                return response()->json([
                    'message'       => "Aucune ligne importée pour '{$sheetImportType}'. Vérifiez les colonnes et les contraintes (FK, champs obligatoires).",
                    'import_type'   => $sheetImportType,
                    'flow_type'     => $flowType,
                    'auto_validate' => $autoValidate,
                    'stored_file'   => $relativePath,
                    'sheet_count'   => 1,
                    'row_count'     => count($dataRows),
                    'headings'      => $headings,
                    'preview'       => array_slice($dataRows, 0, 5),
                    'import_result' => $importResult,
                ], 422);
            }

            return response()->json([
                'message'       => $errorCount > 0
                    ? "Import partiel terminé pour '{$sheetImportType}' ({$created} créé(s), {$updated} mis à jour, {$errorCount} erreur(s))."
                    : 'Import effectué avec succès.',
                'import_type'   => $sheetImportType,
                'flow_type'     => $flowType,
                'auto_validate' => $autoValidate,
                'stored_file'   => $relativePath,
                'sheet_count'   => 1,
                'row_count'     => count($dataRows),
                'headings'      => $headings,
                'preview'       => array_slice($dataRows, 0, 5),
                'import_result' => $importResult,
            ]);
        }

        // ── CAS MULTI-FEUILLES GÉNÉRIQUE ─────────────────────────────────────────
        $sheetsResults = [];
        $unknownSheets = [];
        $globalCreated = 0;
        $globalUpdated = 0;
        $globalErrors  = 0;
        $hasAnySuccess = false;

        foreach ($sheets as $sheet) {
            $sheetName       = $sheet['name'] ?? 'Feuille inconnue';
            $sheetImportType = $this->resolveSheetImportType($sheetName, null);
            if ($sheetImportType === null) {
                $unknownSheets[]  = $sheetName;
                $sheetsResults[]  = [
                    'sheet'       => $sheetName,
                    'import_type' => null,
                    'status'      => 'warning',
                    'message'     => "Feuille [{$sheetName}] ignorée : nom non reconnu comme type d'import valide.",
                ];
                continue;
            }

            if (empty($sheet['data'])) {
                $sheetsResults[] = [
                    'sheet'       => $sheetName,
                    'import_type' => $sheetImportType,
                    'status'      => 'skipped',
                    'message'     => "Feuille [{$sheetName}] vide, ignorée.",
                ];
                continue;
            }

            [$headings, $dataRows] = $this->normalizeSheet($sheet['data']);
            $importResult = $this->dispatchImport($sheetImportType, $headings, $dataRows, $flowType);
            $created    = (int) ($importResult['created'] ?? 0);
            $updated    = (int) ($importResult['updated'] ?? 0);
            $errorCount = count($importResult['errors'] ?? []);

            $globalCreated += $created;
            $globalUpdated += $updated;
            $globalErrors  += $errorCount;

            if ($created > 0 || $updated > 0) {
                $hasAnySuccess = true;
            }

            $sheetsResults[] = [
                'sheet'       => $sheetName,
                'import_type' => $sheetImportType,
                'status'      => ($created === 0 && $updated === 0 && $errorCount > 0) ? 'error' : ($errorCount > 0 ? 'partial' : 'success'),
                'row_count'   => count($dataRows),
                'created'     => $created,
                'updated'     => $updated,
                'errors'      => $importResult['errors'] ?? [],
            ];
        }

        $httpStatus = (!$hasAnySuccess && $globalErrors > 0) ? 422 : 200;

        return response()->json([
            'message'     => $this->buildMultiSheetMessage($globalCreated, $globalUpdated, $globalErrors, $unknownSheets),
            'import_type' => $importType,
            'flow_type'   => $flowType,
            'auto_validate' => $autoValidate,
            'stored_file' => $relativePath,
            'sheet_count' => count($sheets),
            'sheets'      => $sheetsResults,
            'summary'     => [
                'total_created'  => $globalCreated,
                'total_updated'  => $globalUpdated,
                'total_errors'   => $globalErrors,
                'unknown_sheets' => $unknownSheets,
            ],
        ], $httpStatus);
    }

    /**
     * Import d'une production complète depuis un fichier Excel multi-feuilles.
     */
    private function importProduction(array $sheets, string $flowType): array
    {
        $sheetsByName = [];
        foreach ($sheets as $sheet) {
            $normalized = $this->normalizeHeadingKey($sheet['name'] ?? '');
            $sheetsByName[$normalized] = $sheet['data'];
        }

        $required = ['produits', 'production_produits', 'production_materiels'];
        $missing  = array_filter($required, fn($s) => empty($sheetsByName[$s]));
        if (!empty($missing)) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors'  => [['row' => 0, 'errors' => ['Feuilles manquantes : ' . implode(', ', $missing)]]],
            ];
        }

        [$prodHeadings,  $prodRows]     = $this->normalizeSheet($sheetsByName['produits']);
        [$ppHeadings,    $ppRows]       = $this->normalizeSheet($sheetsByName['production_produits']);
        [$pmHeadings,    $pmRows]       = $this->normalizeSheet($sheetsByName['production_materiels']);

        $produitsByTempId   = [];
        foreach ($ppRows as $row) {
            $assoc = $this->rowToAssoc($ppHeadings, $row);
            if ($this->isEmptyRow($assoc)) continue;
            $tempId = (int) ($assoc['production_id'] ?? 0);
            if ($tempId <= 0) continue;
            $produitsByTempId[$tempId][] = $assoc;
        }

        $materielsByTempId = [];
        foreach ($pmRows as $row) {
            $assoc = $this->rowToAssoc($pmHeadings, $row);
            if ($this->isEmptyRow($assoc)) continue;
            $tempId = (int) ($assoc['production_id'] ?? 0);
            if ($tempId <= 0) continue;
            $materielsByTempId[$tempId][] = $assoc;
        }

        $service = app(ProductionService::class);
        $created = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($prodRows as $index => $rowValues) {
            $row = $this->rowToAssoc($prodHeadings, $rowValues);
            if ($this->isEmptyRow($row)) {
                $skipped++;
                continue;
            }

            $tempId   = (int) ($row['id'] ?? 0);
            $produits = $produitsByTempId[$tempId] ?? [];
            $materiels = $materielsByTempId[$tempId] ?? [];

            $resolvedProduits = [];
            foreach ($produits as $p) {
                $refErrors = [];
                $p = $this->resolveFriendlyReferences($p, $refErrors);
                if (!empty($refErrors)) {
                    $errors[] = ['row' => $index + 2, 'errors' => $refErrors];
                    continue 2;
                }
                $resolvedProduits[] = $p;
            }

            $resolvedMateriels = [];
            foreach ($materiels as $m) {
                $refErrors = [];
                $m = $this->resolveFriendlyReferences($m, $refErrors);
                if (!empty($refErrors)) {
                    $errors[] = ['row' => $index + 2, 'errors' => $refErrors];
                    continue 2;
                }
                $resolvedMateriels[] = $m;
            }

            if (empty($resolvedMateriels)) {
                $errors[] = ['row' => $index + 2, 'errors' => ["Production id temporaire {$tempId} : aucun matériel trouvé."]];
                continue;
            }

            try {
                $service->createProduction([
                    'date_prod'      => $this->normalizeDate($row['date_prod'] ?? null),
                    'heure_debut'    => $this->normalizeTime($row['heure_debut'] ?? null),
                    'heure_fin'      => $this->normalizeTime($row['heure_fin'] ?? null),
                    'remarque'       => $row['remarque'] ?? null,
                    'create_user_id' => null,
                    'update_user_id' => null,
                    'produits'       => $resolvedProduits,
                    'materiels'      => $resolvedMateriels,
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index + 2, 'errors' => [$e->getMessage()]];
            }
        }

        return ['created' => $created, 'updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Import d'un bon de commande complet depuis un fichier Excel multi-feuilles.
     */
    private function importBonCommande(array $sheets, string $flowType, bool $autoValidate): array
    {
        $sheetsByName = [];
        foreach ($sheets as $sheet) {
            $normalized = $this->normalizeHeadingKey($sheet['name'] ?? '');
            $sheetsByName[$normalized] = $sheet['data'];
        }

        $required = ['bon_commandes', 'bon_commande_produits'];
        $missing  = array_filter($required, fn($s) => empty($sheetsByName[$s]));
        if (!empty($missing)) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors'  => [['row' => 0, 'errors' => ['Feuilles manquantes : ' . implode(', ', $missing)]]],
            ];
        }

        [$bcHeadings,  $bcRows]     = $this->normalizeSheet($sheetsByName['bon_commandes']);
        [$bcpHeadings, $bcpRows]     = $this->normalizeSheet($sheetsByName['bon_commande_produits']);

        $produitsByTempId = [];
        foreach ($bcpRows as $row) {
            $assoc = $this->rowToAssoc($bcpHeadings, $row);
            if ($this->isEmptyRow($assoc)) continue;
            $tempId = (int) ($assoc['bon_commande_id'] ?? 0);
            if ($tempId <= 0) continue;
            $produitsByTempId[$tempId][] = $assoc;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($bcRows as $index => $rowValues) {
            $row = $this->rowToAssoc($bcHeadings, $rowValues);
            if ($this->isEmptyRow($row)) {
                $skipped++;
                continue;
            }

            $tempId = (int) ($row['id'] ?? 0);
            $produits = $produitsByTempId[$tempId] ?? [];

            $refErrors = [];
            $row = $this->resolveFriendlyReferences($row, $refErrors);
            if (!empty($refErrors)) {
                $errors[] = ['row' => $index + 2, 'errors' => $refErrors];
                continue;
            }

            $resolvedProduits = [];
            foreach ($produits as $p) {
                $prodErrors = [];
                $p = $this->resolveFriendlyReferences($p, $prodErrors);
                if (!empty($prodErrors)) {
                    $errors[] = ['row' => $index + 2, 'errors' => $prodErrors];
                    continue 2;
                }
                $resolvedProduits[] = $p;
            }

            if (empty($resolvedProduits)) {
                $errors[] = ['row' => $index + 2, 'errors' => ["Bon de commande id temporaire {$tempId} : aucun produit trouvé."]];
                continue;
            }

            try {
                DB::transaction(function () use ($row, $resolvedProduits, &$created, &$updated) {
                    $existing = DB::table('bon_commandes')->where('numero', $row['numero'])->first();

                    $payload = $this->filterPayloadByTableColumns($row, Schema::getColumnListing('bon_commandes'));

                    if (isset($payload['date_BC'])) {
                        $payload['date_BC'] = $this->normalizeDate($payload['date_BC']);
                    }
                    if (isset($payload['date_elaboration'])) {
                        $payload['date_elaboration'] = $this->normalizeDate($payload['date_elaboration']);
                    }
                    if (isset($payload['date_prevu_livraison'])) {
                        $payload['date_prevu_livraison'] = $this->normalizeDate($payload['date_prevu_livraison']);
                    }

                    $payload['created_at'] = now();
                    $payload['updated_at'] = now();

                    if ($existing) {
                        DB::table('bon_commandes')->where('id', $existing->id)->update($payload);
                        $bonCommandeId = $existing->id;
                        $updated++;
                    } else {
                        $bonCommandeId = DB::table('bon_commandes')->insertGetId($payload);
                        $created++;
                    }

                    foreach ($resolvedProduits as $produit) {
                        $produitPayload = $this->filterPayloadByTableColumns($produit, Schema::getColumnListing('bon_commande_produits'));
                        $produitPayload['bon_commande_id'] = $bonCommandeId;
                        $produitPayload['created_at'] = now();
                        $produitPayload['updated_at'] = now();
                        DB::table('bon_commande_produits')->insert($produitPayload);
                    }
                });
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index + 2, 'errors' => [$e->getMessage()]];
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Import d'un versement gasoil complet depuis un fichier Excel multi-feuilles.
     */
    private function importGasoil(array $sheets, string $flowType, bool $autoValidate): array
    {
        $sheetsByName = [];
        foreach ($sheets as $sheet) {
            $normalized = $this->normalizeHeadingKey($sheet['name'] ?? '');
            $sheetsByName[$normalized] = $sheet['data'];
        }

        $required = ['bon_gasoils', 'gasoils'];
        $missing  = array_filter($required, fn($s) => empty($sheetsByName[$s]));
        if (!empty($missing)) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors'  => [['row' => 0, 'errors' => ['Feuilles manquantes : ' . implode(', ', $missing)]]],
            ];
        }

        [$bgHeadings,  $bgRows]     = $this->normalizeSheet($sheetsByName['bon_gasoils']);
        [$gHeadings,   $gRows]       = $this->normalizeSheet($sheetsByName['gasoils']);

        $gasoilsByTempId = [];
        foreach ($gRows as $row) {
            $assoc = $this->rowToAssoc($gHeadings, $row);
            if ($this->isEmptyRow($assoc)) continue;
            $tempId = (int) ($assoc['bon_id'] ?? 0);
            if ($tempId <= 0) continue;
            $gasoilsByTempId[$tempId][] = $assoc;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($bgRows as $index => $rowValues) {
            $row = $this->rowToAssoc($bgHeadings, $rowValues);
            if ($this->isEmptyRow($row)) {
                $skipped++;
                continue;
            }

            $tempId = (int) ($row['id'] ?? 0);
            $gasoilOps = $gasoilsByTempId[$tempId] ?? [];

            $refErrors = [];
            $row = $this->resolveFriendlyReferencesExcept($row, $refErrors, ['bon_huile_id', 'bon_gasoil_id']);
            if (!empty($refErrors)) {
                $errors[] = ['row' => $index + 2, 'errors' => $refErrors];
                continue;
            }

            $resolvedGasoilOps = [];
            foreach ($gasoilOps as $g) {
                $opErrors = [];
                $g = $this->resolveFriendlyReferencesExcept($g, $opErrors, ['bon_huile_id', 'bon_gasoil_id']);
                if (!empty($opErrors)) {
                    $errors[] = ['row' => $index + 2, 'errors' => $opErrors];
                    continue 2;
                }
                $resolvedGasoilOps[] = $g;
            }

            if (empty($resolvedGasoilOps)) {
                $errors[] = ['row' => $index + 2, 'errors' => ["Bon gasoil id temporaire {$tempId} : aucune opération trouvée."]];
                continue;
            }

            try {
                DB::transaction(function () use ($row, $resolvedGasoilOps, &$created, $autoValidate) {
                    $service = app(\App\Services\GasoilService::class);

                    foreach ($resolvedGasoilOps as $gasoilOp) {
                        if (empty($gasoilOp['materiel_id_cible'])) {
                            throw new \Exception(
                                "Matériel cible introuvable: '{$gasoilOp['materiel_nom_cible']}'. " .
                                    "Vérifiez que le matériel existe dans la table materiels."
                            );
                        }

                        $isStation = !empty($gasoilOp['source_station']) || $gasoilOp['source_station'] === 'station';

                        if (!$isStation && empty($gasoilOp['source_lieu_stockage_id'])) {
                            throw new \Exception(
                                "Lieu de stockage source introuvable: '{$gasoilOp['source_lieu_stockage_nom']}'. " .
                                    "Vérifiez que le lieu existe dans la table lieu_stockages."
                            );
                        }

                        $versementData = [
                            'num_bon'                 => $row['num_bon'] ?? 'IMP-' . now()->format('YmdHis'),
                            'materiel_id_cible'       => $gasoilOp['materiel_id_cible'],
                            'quantite'                => $gasoilOp['quantite'] ?? $row['quantite'] ?? 0,
                            'source_lieu_stockage_id' => $isStation ? null : ($gasoilOp['source_lieu_stockage_id'] ?? $row['source_lieu_stockage_id'] ?? null),
                            'prix_gasoil'             => $gasoilOp['prix_gasoil'] ?? null,
                            'ajouter_par'             => $gasoilOp['ajouter_par'] ?? $row['ajouter_par'] ?? 'import',
                            'date_operation'          => $this->normalizeDate($row['date_operation'] ?? null),
                        ];

                        $service->createVersement($versementData);
                    }

                    $created++;
                });
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index + 2, 'errors' => [$e->getMessage()]];
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Import d'un versement huile complet depuis un fichier Excel multi-feuilles.
     */
    private function importHuile(array $sheets, string $flowType, bool $autoValidate): array
    {
        $sheetsByName = [];
        foreach ($sheets as $sheet) {
            $normalized = $this->normalizeHeadingKey($sheet['name'] ?? '');
            $sheetsByName[$normalized] = $sheet['data'];
        }

        $required = ['bon_huiles', 'huiles'];
        $missing  = array_filter($required, fn($s) => empty($sheetsByName[$s]));
        if (!empty($missing)) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors'  => [['row' => 0, 'errors' => ['Feuilles manquantes : ' . implode(', ', $missing)]]],
            ];
        }

        [$bhHeadings,  $bhRows]     = $this->normalizeSheet($sheetsByName['bon_huiles']);
        [$hHeadings,   $hRows]       = $this->normalizeSheet($sheetsByName['huiles']);

        $huilesByTempId = [];
        foreach ($hRows as $row) {
            $assoc = $this->rowToAssoc($hHeadings, $row);
            if ($this->isEmptyRow($assoc)) continue;
            $tempId = (int) ($assoc['bon_id'] ?? 0);
            if ($tempId <= 0) continue;
            $huilesByTempId[$tempId][] = $assoc;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($bhRows as $index => $rowValues) {
            $row = $this->rowToAssoc($bhHeadings, $rowValues);
            if ($this->isEmptyRow($row)) {
                $skipped++;
                continue;
            }

            $tempId = (int) ($row['id'] ?? 0);
            $huileOps = $huilesByTempId[$tempId] ?? [];

            $refErrors = [];
            $row = $this->resolveFriendlyReferencesExcept($row, $refErrors, ['bon_gasoil_id', 'bon_huile_id']);
            if (!empty($refErrors)) {
                $errors[] = ['row' => $index + 2, 'errors' => $refErrors];
                continue;
            }

            $resolvedHuileOps = [];
            foreach ($huileOps as $h) {
                $opErrors = [];
                $h = $this->resolveFriendlyReferencesExcept($h, $opErrors, ['bon_gasoil_id', 'bon_huile_id']);
                if (!empty($opErrors)) {
                    $errors[] = ['row' => $index + 2, 'errors' => $opErrors];
                    continue 2;
                }
                $resolvedHuileOps[] = $h;
            }

            if (empty($resolvedHuileOps)) {
                $errors[] = ['row' => $index + 2, 'errors' => ["Bon huile id temporaire {$tempId} : aucune opération trouvée."]];
                continue;
            }

            try {
                DB::transaction(function () use ($row, $resolvedHuileOps, &$created, $autoValidate) {
                    foreach ($resolvedHuileOps as $huileOp) {
                        if (empty($huileOp['materiel_id_cible'])) {
                            throw new \Exception(
                                "Matériel cible introuvable: '{$huileOp['materiel_nom_cible']}'. " .
                                    "Vérifiez que le matériel existe dans la table materiels."
                            );
                        }

                        $isStation = !empty($huileOp['source_station']) || $huileOp['source_station'] === 'station';

                        if (!$isStation && empty($huileOp['source_lieu_stockage_id'])) {
                            throw new \Exception(
                                "Lieu de stockage source introuvable: '{$huileOp['source_lieu_stockage_nom']}'. " .
                                    "Vérifiez que le lieu existe dans la table lieu_stockages."
                            );
                        }

                        if (empty($huileOp['article_versement_id'])) {
                            throw new \Exception(
                                "article_versement_id est requis. Vérifiez que article_versement_nom est rempli dans l'Excel."
                            );
                        }

                        $huileData = [
                            'bon_id'                  => null,
                            'type_operation'          => $huileOp['type_operation'] ?? 'versement',
                            'materiel_id_cible'       => $huileOp['materiel_id_cible'],
                            'materiel_id_source'      => $huileOp['materiel_id_source'] ?? null,
                            'source_lieu_stockage_id' => $isStation ? null : ($huileOp['source_lieu_stockage_id'] ?? null),
                            'source_station'          => $isStation ? 'station' : null,
                            'quantite'                => $huileOp['quantite'] ?? $row['quantite'] ?? 0,
                            'prix_total'              => $huileOp['prix_total'] ?? null,
                            'ajouter_par'             => $huileOp['ajouter_par'] ?? $row['ajouter_par'] ?? 'import',
                            'is_consumed'             => $this->toBoolean($huileOp['is_consumed'] ?? true),
                            'subdivision_id_cible'    => $huileOp['subdivision_id_cible'] ?? null,
                            'subdivision_id_source'   => $huileOp['subdivision_id_source'] ?? null,
                            'article_versement_id'    => $huileOp['article_versement_id'] ?? null,
                            'quantite_stock_avant'    => $huileOp['quantite_stock_avant'] ?? null,
                            'quantite_stock_apres'    => $huileOp['quantite_stock_apres'] ?? null,
                            'created_at'              => now(),
                            'updated_at'              => now(),
                        ];

                        $bonHuileData = [
                            'num_bon'                 => $row['num_bon'] ?? 'IMP-H-' . now()->format('YmdHis'),
                            'source_lieu_stockage_id' => $huileData['source_lieu_stockage_id'],
                            'ajouter_par'             => $huileData['ajouter_par'],
                            'created_at'              => now(),
                            'updated_at'              => now(),
                        ];

                        $bonId = DB::table('bon_huiles')->insertGetId($bonHuileData);
                        $huileData['bon_id'] = $bonId;

                        DB::table('huiles')->insert($huileData);

                        if (!$isStation && !empty($huileData['source_lieu_stockage_id']) && !empty($huileData['article_versement_id'])) {
                            $stock = DB::table('stocks')
                                ->where('article_id', $huileData['article_versement_id'])
                                ->where('lieu_stockage_id', $huileData['source_lieu_stockage_id'])
                                ->lockForUpdate()
                                ->first();

                            if (!$stock || $stock->quantite < $huileData['quantite']) {
                                $dispo = $stock ? $stock->quantite : 0;
                                throw new \Exception(
                                    "Stock huile insuffisant. Disponible: {$dispo} L, Demandé: {$huileData['quantite']} L."
                                );
                            }

                            $stockAvant = (float) $stock->quantite;
                            DB::table('stocks')
                                ->where('id', $stock->id)
                                ->update(['quantite' => $stockAvant - $huileData['quantite']]);

                            DB::table('huiles')
                                ->where('bon_id', $bonId)
                                ->update([
                                    'quantite_stock_avant' => $stockAvant,
                                    'quantite_stock_apres' => $stockAvant - $huileData['quantite'],
                                ]);
                        }
                    }

                    $created++;
                });
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index + 2, 'errors' => [$e->getMessage()]];
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Import de bons de livraison en mono-feuille (avec appel au Service).
     */
    private function importBonLivraisonMonoFeuille(array $headings, array $dataRows): array
    {
        $created = 0;
        $errors  = [];
        $skipped = 0;

        foreach ($dataRows as $index => $rowValues) {
            $row = $this->rowToAssoc($headings, $rowValues);

            if ($this->isEmptyRow($row)) {
                $skipped++;
                continue;
            }

            // Résolution des FK
            $refErrors = [];
            $row = $this->resolveFriendlyReferences($row, $refErrors);
            if (!empty($refErrors)) {
                $errors[] = ['row' => $index + 2, 'errors' => $refErrors];
                continue;
            }

            // Normaliser les dates et heures
            $row['date_livraison'] = $this->normalizeDate($row['date_livraison'] ?? null);
            $row['heure_depart'] = $this->normalizeTime($row['heure_depart'] ?? null);
            $row['date_arriver'] = $this->normalizeDate($row['date_arriver'] ?? null);
            $row['heure_arrive'] = $this->normalizeTime($row['heure_arrive'] ?? null);

            // Vérifier les champs obligatoires
            $requiredFields = ['numbl', 'date_livraison', 'heure_depart', 'vehicule_id', 'chauffeur_id', 'bon_commande_produit_id', 'quantite', 'pu', 'gasoil_depart', 'nbr_voyage'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (empty($row[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                $errors[] = ['row' => $index + 2, 'errors' => ['Champs obligatoires manquants : ' . implode(', ', $missingFields)]];
                continue;
            }

            try {
                DB::transaction(function () use ($row, &$created) {
                    $service = app(\App\Services\BonLivraisonService::class);

                    $blData = [
                        'numBL'                   => $row['numbl'],
                        'date_livraison'          => $row['date_livraison'],
                        'heure_depart'            => $row['heure_depart'],
                        'vehicule_id'             => $row['vehicule_id'],
                        'chauffeur_id'            => $row['chauffeur_id'],
                        'aide_chauffeur_id'       => $row['aide_chauffeur_id'] ?? null,
                        'bon_commande_produit_id' => $row['bon_commande_produit_id'],
                        'quantite'                => $row['quantite'],
                        'PU'                      => $row['pu'],
                        'gasoil_depart'           => $row['gasoil_depart'],
                        'compteur_depart'         => $row['compteur_depart'] ?? null,
                        'nbr_voyage'              => $row['nbr_voyage'] ?? '1',
                        'remarque'                => $row['remarque'] ?? null,
                        'isDelivred'              => $row['isdelivred'] ?? 0,
                        'user_name'               => 'import',
                        'heure_chauffeur'         => $row['heure_chauffeur'] ?? null,
                        'heure_machine'           => $row['heure_machine'] ?? 0,
                        'heure_arrive'            => $row['heure_arrive'] ?? null,
                        'gasoil_arrive'           => $row['gasoil_arrive'] ?? null,
                        'date_arriver'            => $row['date_arriver'] ?? null,
                        'compteur_arrive'         => $row['compteur_arrive'] ?? null,
                        'distance'                => $row['distance'] ?? null,
                        'consommation_reelle_par_heure' => $row['consommation_reelle_par_heure'] ?? null,
                        'consommation_horaire_reference' => $row['consommation_horaire_reference'] ?? null,
                        'ecart_consommation_horaire' => $row['ecart_consommation_horaire'] ?? null,
                        'statut_consommation_horaire' => $row['statut_consommation_horaire'] ?? null,
                        'consommation_totale'    => $row['consommation_totale'] ?? null,
                        'consommation_destination_reference' => $row['consommation_destination_reference'] ?? null,
                        'ecart_consommation_destination' => $row['ecart_consommation_destination'] ?? null,
                        'statut_consommation_destination' => $row['statut_consommation_destination'] ?? null,
                    ];

                    // Si BL déjà validé (isDelivred = 1), ajouter les champs d'arrivée
                    // if (!empty($row['isdelivred']) || !empty($row['date_arriver'])) {
                    //     $blData = array_merge($blData, [
                    //         'heure_arrive'    => $row['heure_arrive'],
                    //         'gasoil_arrive'   => $row['gasoil_arrive'] ?? null,
                    //         'date_arriver'    => $row['date_arriver'],
                    //         'compteur_arrive' => $row['compteur_arrive'] ?? null,
                    //         'distance'        => $row['distance'] ?? null,
                    //     ]);
                    // }

                    $service->createBonLivraison($blData);
                    $created++;
                });
            } catch (\Throwable $e) {
                \Log::error('Erreur import BL', [
                    'row' => $index + 2,
                    'error' => $e->getMessage(),
                    'data' => $row
                ]);
                $errors[] = ['row' => $index + 2, 'errors' => [$e->getMessage()]];
            }
        }

        return ['created' => $created, 'updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Import combiné : pneus + historique_pneus (2 feuilles)
     */
    private function importPneuCombiné(array $sheets, string $flowType): array
    {
        $sheetsByName = [];
        foreach ($sheets as $sheet) {
            $normalized = $this->normalizeHeadingKey($sheet['name'] ?? '');
            $sheetsByName[$normalized] = $sheet['data'];
        }

        $required = ['pneus', 'historique_pneus'];
        $missing = array_filter($required, fn($s) => empty($sheetsByName[$s]));
        if (!empty($missing)) {
            return [
                'pneu_created' => 0,
                'historique_created' => 0,
                'errors' => [['row' => 0, 'errors' => ['Feuilles manquantes : ' . implode(', ', $missing)]]],
            ];
        }

        [$pneuHeadings, $pneuRows] = $this->normalizeSheet($sheetsByName['pneus']);
        [$histHeadings, $histRows] = $this->normalizeSheet($sheetsByName['historique_pneus']);

        // Indexer les lignes d'historique par pneu_id temporaire
        $historiqueByTempId = [];
        foreach ($histRows as $row) {
            $assoc = $this->rowToAssoc($histHeadings, $row);
            if ($this->isEmptyRow($assoc)) continue;
            $tempId = (int) ($assoc['pneu_id'] ?? 0);
            if ($tempId <= 0) continue;
            $historiqueByTempId[$tempId][] = $assoc;
        }

        $pneuCreated = 0;
        $pneuUpdated = 0;
        $historiqueCreated = 0;
        $errors = [];

        $pneuTableColumns = Schema::getColumnListing('pneus');
        $histTableColumns = Schema::getColumnListing('historique_pneus');
        $now = now();

        foreach ($pneuRows as $index => $rowValues) {
            $row = $this->rowToAssoc($pneuHeadings, $rowValues);
            if ($this->isEmptyRow($row)) continue;

            $tempId = (int) ($row['id'] ?? 0);
            $historiqueRows = $historiqueByTempId[$tempId] ?? [];

            // Transformer les colonnes contenant des noms
            if (isset($row['materiel_id']) && !empty($row['materiel_id']) && !is_numeric($row['materiel_id'])) {
                $row['materiel_nom'] = $row['materiel_id'];
                unset($row['materiel_id']);
            }
            if (isset($row['lieu_stockages_id']) && !empty($row['lieu_stockages_id']) && !is_numeric($row['lieu_stockages_id'])) {
                $row['lieu_stockage_nom'] = $row['lieu_stockages_id'];
                unset($row['lieu_stockages_id']);
            }

            $row = $this->normalizePneuImportRow($row);
            // Résoudre les références du pneu
            $refErrors = [];
            $row = $this->resolveFriendlyReferences($row, $refErrors);
            if (!empty($refErrors)) {
                $errors[] = ['row' => $index + 2, 'type' => 'pneu', 'errors' => $refErrors];
                continue;
            }

            if (empty($row['num_serie'])) {
                $errors[] = ['row' => $index + 2, 'type' => 'pneu', 'errors' => ['num_serie est requis']];
                continue;
            }

            // Normaliser les dates
            $row['date_obtention'] = $this->normalizeDate($row['date_obtention'] ?? null);
            $row['date_mise_en_service'] = $this->normalizeDate($row['date_mise_en_service'] ?? null);
            $row['date_mise_hors_service'] = $this->normalizeDate($row['date_mise_hors_service'] ?? null);

            $payload = $this->filterPayloadByTableColumns($row, $pneuTableColumns);
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;

            try {
                DB::transaction(function () use ($payload, $historiqueRows, $histTableColumns, $now, &$pneuCreated, &$pneuUpdated, &$historiqueCreated, $tempId, &$errors, $index) {
                    // Insérer ou mettre à jour le pneu
                    $existing = DB::table('pneus')->where('num_serie', $payload['num_serie'])->first();
                    if ($existing) {
                        DB::table('pneus')->where('id', $existing->id)->update($payload);
                        $pneuId = $existing->id;
                        $pneuUpdated++;
                    } else {
                        $pneuId = DB::table('pneus')->insertGetId($payload);
                        $pneuCreated++;
                    }

                    // Insérer les lignes d'historique associées
                    foreach ($historiqueRows as $histRow) {
                        $histRow = $this->normalizeHistoriquePneuImportRow($histRow);
                        if (!empty($histRow['__skip_history'])) {
                            continue;
                        }

                        $histErrors = [];
                        $histRow = $this->resolveFriendlyReferences($histRow, $histErrors);
                        if (!empty($histErrors)) {
                            $errors[] = ['row' => $index + 2, 'type' => 'historique_pneu', 'errors' => $histErrors];
                            continue;
                        }

                        $histRow['date_action'] = $this->normalizeDate($histRow['date_action'] ?? null);
                        if (empty($histRow['date_action'])) {
                            $histRow['date_action'] = $payload['date_mise_en_service'] ?? $payload['date_obtention'] ?? $now->toDateString();
                        }


                        $histPayload = $this->filterPayloadByTableColumns($histRow, $histTableColumns);
                        $histPayload['pneu_id'] = $pneuId;
                        $histPayload['created_at'] = $now;
                        $histPayload['updated_at'] = $now;

                        DB::table('historique_pneus')->insert($histPayload);
                        $historiqueCreated++;
                    }
                });
            } catch (Throwable $e) {
                $errors[] = ['row' => $index + 2, 'type' => 'pneu', 'errors' => [$e->getMessage()]];
            }
        }

        return [
            'pneu_created' => $pneuCreated,
            'pneu_updated' => $pneuUpdated,
            'historique_created' => $historiqueCreated,
            'errors' => $errors,
        ];
    }

    /**
     * Import combiné : fournitures + historique_fournitures (2 feuilles)
     */
    private function importFournitureCombine(array $sheets, string $flowType): array
    {
        $sheetsByName = [];
        foreach ($sheets as $sheet) {
            $normalized = $this->normalizeHeadingKey($sheet['name'] ?? '');
            $sheetsByName[$normalized] = $sheet['data'];
        }

        $required = ['fournitures', 'historique_fournitures'];
        $missing = array_filter($required, fn($s) => empty($sheetsByName[$s]));
        if (!empty($missing)) {
            return [
                'fourniture_created' => 0,
                'historique_created' => 0,
                'errors' => [['row' => 0, 'errors' => ['Feuilles manquantes : ' . implode(', ', $missing)]]],
            ];
        }

        [$fournHeadings, $fournRows] = $this->normalizeSheet($sheetsByName['fournitures']);
        [$histHeadings, $histRows] = $this->normalizeSheet($sheetsByName['historique_fournitures']);

        // Indexer les lignes d'historique par fourniture_id temporaire
        $historiqueByTempId = [];
        foreach ($histRows as $row) {
            $assoc = $this->rowToAssoc($histHeadings, $row);
            if ($this->isEmptyRow($assoc)) continue;
            $tempId = (int) ($assoc['fourniture_id'] ?? 0);
            if ($tempId <= 0) continue;
            $historiqueByTempId[$tempId][] = $assoc;
        }

        $fournCreated = 0;
        $fournUpdated = 0;
        $historiqueCreated = 0;
        $errors = [];

        $fournTableColumns = Schema::getColumnListing('fournitures');
        $histTableColumns = Schema::getColumnListing('historique_fournitures');
        $now = now();

        foreach ($fournRows as $index => $rowValues) {
            $row = $this->rowToAssoc($fournHeadings, $rowValues);
            if ($this->isEmptyRow($row)) continue;

            $tempId = (int) ($row['id'] ?? 0);
            $historiqueRows = $historiqueByTempId[$tempId] ?? [];

            // Transformation des colonnes pour la fourniture
            if (isset($row['materiel_id_associe']) && !empty($row['materiel_id_associe']) && !is_numeric($row['materiel_id_associe'])) {
                $row['materiel_nom_associe'] = $row['materiel_id_associe'];
                unset($row['materiel_id_associe']);
            }
            if (isset($row['lieu_stockage_id']) && !empty($row['lieu_stockage_id']) && !is_numeric($row['lieu_stockage_id'])) {
                $row['lieu_stockage_nom'] = $row['lieu_stockage_id'];
                unset($row['lieu_stockage_id']);
            }

            // Résoudre les références de la fourniture
            $refErrors = [];
            $row = $this->resolveFriendlyReferences($row, $refErrors);
            if (!empty($refErrors)) {
                $errors[] = ['row' => $index + 2, 'type' => 'fourniture', 'errors' => $refErrors];
                continue;
            }

            if (empty($row['nom_article']) || empty($row['reference'])) {
                $errors[] = ['row' => $index + 2, 'type' => 'fourniture', 'errors' => ['nom_article et reference sont requis']];
                continue;
            }

            // Normaliser les dates
            $row['date_acquisition'] = $this->normalizeDate($row['date_acquisition'] ?? null);
            $row['date_sortie_stock'] = $this->normalizeDate($row['date_sortie_stock'] ?? null);
            $row['date_retour_stock'] = $this->normalizeDate($row['date_retour_stock'] ?? null);

            // Convertir les booléens
            if (isset($row['is_dispo'])) {
                $row['is_dispo'] = $this->toBoolean($row['is_dispo']);
            }

            $payload = $this->filterPayloadByTableColumns($row, $fournTableColumns);
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;

            try {
                DB::transaction(function () use ($payload, $historiqueRows, $histTableColumns, $now, &$fournCreated, &$fournUpdated, &$historiqueCreated, $tempId, &$errors, $index) {
                    // Insérer ou mettre à jour la fourniture
                    $fournitureId = null;
                    if (!empty($payload['numero_serie'])) {
                        $existing = DB::table('fournitures')->where('numero_serie', $payload['numero_serie'])->first();
                        if ($existing) {
                            DB::table('fournitures')->where('id', $existing->id)->update($payload);
                            $fournitureId = $existing->id;
                            $fournUpdated++;
                        }
                    }
                    if (!$fournitureId) {
                        $fournitureId = DB::table('fournitures')->insertGetId($payload);
                        $fournCreated++;
                    }

                    // Insérer les lignes d'historique associées
                    foreach ($historiqueRows as $histRow) {
                        // Résoudre les autres références (sauf les champs de noms de matériel)
                        $histErrors = [];
                        $histRow = $this->resolveFriendlyReferencesExcept($histRow, $histErrors, ['ancien_materiel_nom', 'nouveau_materiel_nom']);
                        if (!empty($histErrors)) {
                            $errors[] = ['row' => $index + 2, 'type' => 'historique_fourniture', 'errors' => $histErrors];
                            continue;
                        }

                        // --- Traitement manuel amélioré pour ancien_materiel_nom ---
                        if (!empty($histRow['ancien_materiel_nom'])) {
                            $nom = trim($histRow['ancien_materiel_nom']);
                            $materiel = DB::table('materiels')
                                ->whereRaw('LOWER(TRIM(nom_materiel)) = ?', [strtolower($nom)])
                                ->first();
                            if ($materiel) {
                                $histRow['ancien_materiel_id'] = $materiel->id;
                                // On conserve la valeur textuelle (ne pas la vider) pour traçabilité
                            } else {
                                $histRow['ancien_materiel_id'] = null;
                            }
                        } else {
                            $histRow['ancien_materiel_id'] = null;
                        }

                        // --- Traitement manuel amélioré pour nouveau_materiel_nom ---
                        if (!empty($histRow['nouveau_materiel_nom'])) {
                            $nom = trim($histRow['nouveau_materiel_nom']);
                            $materiel = DB::table('materiels')
                                ->whereRaw('LOWER(TRIM(nom_materiel)) = ?', [strtolower($nom)])
                                ->first();
                            if ($materiel) {
                                $histRow['nouveau_materiel_id'] = $materiel->id;
                                // On conserve la valeur textuelle
                            } else {
                                $histRow['nouveau_materiel_id'] = null;
                            }
                        } else {
                            $histRow['nouveau_materiel_id'] = null;
                        }

                        $histRow['date_action'] = $this->normalizeDate($histRow['date_action'] ?? null);

                        $histPayload = $this->filterPayloadByTableColumns($histRow, $histTableColumns);
                        $histPayload['fourniture_id'] = $fournitureId;
                        $histPayload['created_at'] = $now;
                        $histPayload['updated_at'] = $now;

                        DB::table('historique_fournitures')->insert($histPayload);
                        $historiqueCreated++;
                    }
                });
            } catch (Throwable $e) {
                $errors[] = ['row' => $index + 2, 'type' => 'fourniture', 'errors' => [$e->getMessage()]];
            }
        }

        return [
            'fourniture_created' => $fournCreated,
            'fourniture_updated' => $fournUpdated,
            'historique_created' => $historiqueCreated,
            'errors' => $errors,
        ];
    }
    /**
     * Import combiné Bon de Commande + Bon de Livraison (3 feuilles)
     */
    private function importBonCommandeEtLivraison(array $sheets, string $flowType): array
    {
        $sheetsByName = [];
        foreach ($sheets as $sheet) {
            $normalized = $this->normalizeHeadingKey($sheet['name'] ?? '');
            $sheetsByName[$normalized] = $sheet['data'];
        }

        $required = ['bon_commandes', 'bon_commande_produits', 'bon_livraisons'];
        $missing  = array_filter($required, fn($s) => empty($sheetsByName[$s]));
        if (!empty($missing)) {
            return [
                'bon_commande_created' => 0,
                'bon_livraison_created' => 0,
                'errors'  => [['row' => 0, 'errors' => ['Feuilles manquantes : ' . implode(', ', $missing)]]],
            ];
        }

        [$bcHeadings,  $bcRows]  = $this->normalizeSheet($sheetsByName['bon_commandes']);
        [$bcpHeadings, $bcpRows] = $this->normalizeSheet($sheetsByName['bon_commande_produits']);
        [$blHeadings,  $blRows]   = $this->normalizeSheet($sheetsByName['bon_livraisons']);

        // Indexer les produits par bon_commande_id temporaire
        $produitsByTempId = [];
        foreach ($bcpRows as $row) {
            $assoc = $this->rowToAssoc($bcpHeadings, $row);
            if ($this->isEmptyRow($assoc)) continue;
            $tempId = (int) ($assoc['bon_commande_id'] ?? 0);
            if ($tempId <= 0) continue;
            $produitsByTempId[$tempId][] = $assoc;
        }

        // Mapper les IDs temporaires des produits vers les vrais IDs (sera rempli après création)
        $bcProduitTempIdMap = [];
        foreach ($bcpRows as $row) {
            $assoc = $this->rowToAssoc($bcpHeadings, $row);
            if ($this->isEmptyRow($assoc)) continue;
            $tempId = (int) ($assoc['id'] ?? 0);
            if ($tempId > 0) {
                $bcProduitTempIdMap[$tempId] = null;
            }
        }

        $bcCreated = 0;
        $bcErrors  = [];
        $bcIdMap   = []; // Map temp_id → vrai ID du BC en base

        // ---- 1. Création des BC et de leurs produits ----
        foreach ($bcRows as $index => $rowValues) {
            $row = $this->rowToAssoc($bcHeadings, $rowValues);
            if ($this->isEmptyRow($row)) continue;

            $tempId = (int) ($row['id'] ?? 0);
            $produits = $produitsByTempId[$tempId] ?? [];

            if (empty($produits)) {
                $bcErrors[] = ['row' => $index + 2, 'type' => 'bon_commande', 'errors' => ["BC id {$tempId} : aucun produit trouvé dans la feuille bon_commande_produits."]];
                continue;
            }

            // Résoudre les références du BC lui-même (client, destination, …)
            $refErrors = [];
            $row = $this->resolveFriendlyReferences($row, $refErrors);
            if (!empty($refErrors)) {
                $bcErrors[] = ['row' => $index + 2, 'type' => 'bon_commande', 'errors' => $refErrors];
                continue;
            }

            $resolvedProduits = [];
            foreach ($produits as $p) {
                // ÉTAPE 1 : Résoudre les noms des produits en IDs
                $prodErrors = [];
                $pResolved = $this->resolveFriendlyReferences($p, $prodErrors);
                if (!empty($prodErrors)) {
                    $bcErrors[] = ['row' => $index + 2, 'type' => 'bon_commande_produit', 'errors' => $prodErrors];
                    continue 2; // On passe au BC suivant
                }

                // ÉTAPE 2 : Vérifier que les IDs obligatoires sont présents
                if (empty($pResolved['article_id'])) {
                    $bcErrors[] = ['row' => $index + 2, 'type' => 'bon_commande_produit', 'errors' => [
                        "article_id non résolu pour '{$pResolved['article_nom']}'. Vérifiez que l'article existe dans article_depots."
                    ]];
                    continue 2;
                }
                if (empty($pResolved['lieu_stockage_id'])) {
                    $bcErrors[] = ['row' => $index + 2, 'type' => 'bon_commande_produit', 'errors' => [
                        "lieu_stockage_id non résolu pour '{$pResolved['lieu_stockage_nom']}'. Vérifiez que le lieu existe dans lieu_stockages."
                    ]];
                    continue 2;
                }

                // ÉTAPE 3 : Filtrer pour ne garder que les colonnes de la table bon_commande_produits
                $produitPayload = $this->filterPayloadByTableColumns($pResolved, Schema::getColumnListing('bon_commande_produits'));

                $resolvedProduits[] = $produitPayload;
            }

            if (empty($resolvedProduits)) {
                $bcErrors[] = ['row' => $index + 2, 'type' => 'bon_commande', 'errors' => ["BC id {$tempId} : aucun produit valide après résolution."]];
                continue;
            }

            try {
                DB::transaction(function () use ($row, $resolvedProduits, &$bcCreated, &$bcIdMap, &$bcProduitTempIdMap, $tempId, $produits) {
                    // Vérifier si le BC existe déjà (par son numéro)
                    $existing = DB::table('bon_commandes')->where('numero', $row['numero'])->first();
                    $payload = $this->filterPayloadByTableColumns($row, Schema::getColumnListing('bon_commandes'));

                    // Normaliser les dates
                    if (isset($payload['date_BC'])) {
                        $payload['date_BC'] = $this->normalizeDate($payload['date_BC']);
                    }
                    if (isset($payload['date_elaboration'])) {
                        $payload['date_elaboration'] = $this->normalizeDate($payload['date_elaboration']);
                    }
                    if (isset($payload['date_prevu_livraison'])) {
                        $payload['date_prevu_livraison'] = $this->normalizeDate($payload['date_prevu_livraison']);
                    }

                    $payload['created_at'] = now();
                    $payload['updated_at'] = now();

                    if ($existing) {
                        DB::table('bon_commandes')->where('id', $existing->id)->update($payload);
                        $bcIdMap[$tempId] = $existing->id;
                    } else {
                        $bonCommandeId = DB::table('bon_commandes')->insertGetId($payload);
                        $bcIdMap[$tempId] = $bonCommandeId;
                        $bcCreated++;
                    }

                    // Insérer les produits liés
                    foreach ($resolvedProduits as $i => $produitPayload) {
                        $produitAssocOriginal = $produits[$i]; // pour récupérer l'ID temporaire
                        $produitTempId = (int) ($produitAssocOriginal['id'] ?? 0);

                        $produitPayload['bon_commande_id'] = $bcIdMap[$tempId];
                        $produitPayload['created_at'] = now();
                        $produitPayload['updated_at'] = now();

                        $bcProduitId = DB::table('bon_commande_produits')->insertGetId($produitPayload);

                        if ($produitTempId > 0) {
                            $bcProduitTempIdMap[$produitTempId] = $bcProduitId;
                        }
                    }
                });
            } catch (\Throwable $e) {
                $bcErrors[] = ['row' => $index + 2, 'type' => 'bon_commande', 'errors' => [$e->getMessage()]];
            }
        }

        // ---- 2. Import des bons de livraison (BL) ----
        $blCreated = 0;
        $blErrors  = [];

        foreach ($blRows as $index => $rowValues) {
            $row = $this->rowToAssoc($blHeadings, $rowValues);
            if ($this->isEmptyRow($row)) continue;

            // ÉTAPE 1 : Remplacer l'ID temporaire du produit par le vrai ID
            $bcProduitTempId = (int) ($row['bon_commande_produit_id'] ?? 0);
            if ($bcProduitTempId > 0) {
                if (!isset($bcProduitTempIdMap[$bcProduitTempId]) || $bcProduitTempIdMap[$bcProduitTempId] === null) {
                    $blErrors[] = ['row' => $index + 2, 'type' => 'bon_livraison', 'errors' => [
                        "bon_commande_produit_id temporaire {$bcProduitTempId} introuvable. " .
                            "Vérifiez que : 1) L'ID existe dans la feuille bon_commande_produits, " .
                            "2) Le produit a été créé avec succès (article_id et lieu_stockage_id valides)."
                    ]];
                    continue;
                }
                $row['bon_commande_produit_id'] = $bcProduitTempIdMap[$bcProduitTempId];
            } else {
                $blErrors[] = ['row' => $index + 2, 'type' => 'bon_livraison', 'errors' => ["bon_commande_produit_id manquant ou invalide."]];
                continue;
            }

            // ÉTAPE 2 : Résoudre les autres références (véhicule, chauffeur, client, …)
            $refErrors = [];
            $row = $this->resolveFriendlyReferencesExcept($row, $refErrors, ['bon_commande_produit_id']);
            if (!empty($refErrors)) {
                $blErrors[] = ['row' => $index + 2, 'type' => 'bon_livraison', 'errors' => $refErrors];
                continue;
            }

            // Vérifier que le client_id a bien été résolu
            if (empty($row['client_id'])) {
                $blErrors[] = ['row' => $index + 2, 'type' => 'bon_livraison', 'errors' => [
                    "client_id non résolu. Vérifiez que le client_nom existe dans la table clients."
                ]];
                continue;
            }

            // Normaliser dates et heures
            $row['date_livraison'] = $this->normalizeDate($row['date_livraison'] ?? null);
            $row['heure_depart'] = $this->normalizeTime($row['heure_depart'] ?? null);
            $row['date_arriver'] = $this->normalizeDate($row['date_arriver'] ?? null);
            $row['heure_arrive'] = $this->normalizeTime($row['heure_arrive'] ?? null);

            // Vérifier les champs obligatoires
            $requiredFields = ['numbl', 'date_livraison', 'heure_depart', 'vehicule_id', 'chauffeur_id', 'bon_commande_produit_id', 'quantite', 'pu', 'gasoil_depart', 'nbr_voyage'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (empty($row[$field])) {
                    $missingFields[] = $field;
                }
            }
            if (!empty($missingFields)) {
                $blErrors[] = ['row' => $index + 2, 'type' => 'bon_livraison', 'errors' => ['Champs obligatoires manquants : ' . implode(', ', $missingFields)]];
                continue;
            }

            try {
                DB::transaction(function () use ($row, &$blCreated) {
                    $service = app(\App\Services\BonLivraisonService::class);
                    $blData = [
                        'numBL'                   => $row['numbl'],
                        'date_livraison'          => $row['date_livraison'],
                        'heure_depart'            => $row['heure_depart'],
                        'vehicule_id'             => $row['vehicule_id'],
                        'chauffeur_id'            => $row['chauffeur_id'],
                        'client_id'                => $row['client_id'], // AJOUTÉ
                        'aide_chauffeur_id'       => $row['aide_chauffeur_id'] ?? null,
                        'bon_commande_produit_id' => $row['bon_commande_produit_id'],
                        'quantite'                => $row['quantite'],
                        'PU'                      => $row['pu'],
                        'gasoil_depart'           => $row['gasoil_depart'],
                        'compteur_depart'         => $row['compteur_depart'] ?? null,
                        'nbr_voyage'              => $row['nbr_voyage'] ?? '1',
                        'remarque'                => $row['remarque'] ?? null,
                        'isDelivred'              => $row['isdelivred'] ?? 0,
                        'user_name'               => 'import',
                        'heure_chauffeur'         => $row['heure_chauffeur'] ?? null,
                        'heure_machine'           => $row['heure_machine'] ?? 0,
                        'heure_arrive'            => $row['heure_arrive'] ?? null,
                        'gasoil_arrive'           => $row['gasoil_arrive'] ?? null,
                        'date_arriver'            => $row['date_arriver'] ?? null,
                        'compteur_arrive'         => $row['compteur_arrive'] ?? null,
                        'distance'                => $row['distance'] ?? null,
                        'consommation_reelle_par_heure' => $row['consommation_reelle_par_heure'] ?? null,
                        'consommation_horaire_reference' => $row['consommation_horaire_reference'] ?? null,
                        'ecart_consommation_horaire' => $row['ecart_consommation_horaire'] ?? null,
                        'statut_consommation_horaire' => $row['statut_consommation_horaire'] ?? null,
                        'consommation_totale'    => $row['consommation_totale'] ?? null,
                        'consommation_destination_reference' => $row['consommation_destination_reference'] ?? null,
                        'ecart_consommation_destination' => $row['ecart_consommation_destination'] ?? null,
                        'statut_consommation_destination' => $row['statut_consommation_destination'] ?? null,
                    ];

                    $service->createBonLivraison($blData);
                    $blCreated++;
                });
            } catch (\Throwable $e) {
                \Log::error('Erreur import BL', [
                    'row' => $index + 2,
                    'error' => $e->getMessage(),
                    'data' => $row
                ]);
                $blErrors[] = ['row' => $index + 2, 'type' => 'bon_livraison', 'errors' => [$e->getMessage()]];
            }
        }

        return [
            'bon_commande_created'  => $bcCreated,
            'bon_livraison_created' => $blCreated,
            'errors'                => array_merge($bcErrors, $blErrors),
        ];
    }

    /**
     * Import combiné Bon de Transfert + Transfert Produits (2 feuilles)
     */
    private function importTransfert(array $sheets, string $flowType): array
    {
        $sheetsByName = [];
        foreach ($sheets as $sheet) {
            $normalized = $this->normalizeHeadingKey($sheet['name'] ?? '');
            $sheetsByName[$normalized] = $sheet['data'];
        }

        $required = ['bon_transferts', 'transfert_produits'];
        $missing  = array_filter($required, fn($s) => empty($sheetsByName[$s]));
        if (!empty($missing)) {
            return [
                'bon_transfert_created'   => 0,
                'transfert_produit_created' => 0,
                'errors' => [['row' => 0, 'errors' => ['Feuilles manquantes : ' . implode(', ', $missing)]]],
            ];
        }

        [$btHeadings, $btRows] = $this->normalizeSheet($sheetsByName['bon_transferts']);
        [$tpHeadings, $tpRows] = $this->normalizeSheet($sheetsByName['transfert_produits']);

        // Indexer les produits de transfert par bon_transfert_id temporaire
        $produitsByTempId = [];
        foreach ($tpRows as $row) {
            $assoc = $this->rowToAssoc($tpHeadings, $row);
            if ($this->isEmptyRow($assoc)) continue;
            $tempId = (int) ($assoc['bon_transfert_id'] ?? 0);
            if ($tempId <= 0) continue;
            $produitsByTempId[$tempId][] = $assoc;
        }

        $btCreated = 0;
        $btUpdated = 0;
        $tpCreated = 0;
        $errors = [];

        // Parcourir chaque bon de transfert
        foreach ($btRows as $index => $rowValues) {
            $row = $this->rowToAssoc($btHeadings, $rowValues);
            if ($this->isEmptyRow($row)) continue;

            $tempId = (int) ($row['id'] ?? 0);
            $produits = $produitsByTempId[$tempId] ?? [];

            if (empty($produits)) {
                $errors[] = ['row' => $index + 2, 'type' => 'bon_transfert', 'errors' => ["Bon transfert id {$tempId} : aucun produit trouvé dans la feuille transfert_produits."]];
                continue;
            }

            // Résoudre les références du bon de transfert lui-même
            $refErrors = [];
            $row = $this->resolveFriendlyReferences($row, $refErrors);
            if (!empty($refErrors)) {
                $errors[] = ['row' => $index + 2, 'type' => 'bon_transfert', 'errors' => $refErrors];
                continue;
            }

            // Vérifier les champs obligatoires résolus
            $requiredBtFields = ['produit_id', 'lieu_stockage_depart_id', 'lieu_stockage_arrive_id', 'unite_id', 'user_id'];
            $missingBt = [];
            foreach ($requiredBtFields as $field) {
                if (empty($row[$field])) {
                    $missingBt[] = $field;
                }
            }
            if (!empty($missingBt)) {
                $errors[] = ['row' => $index + 2, 'type' => 'bon_transfert', 'errors' => ["Champs obligatoires non résolus : " . implode(', ', $missingBt)]];
                continue;
            }

            // Traiter les produits associés
            $resolvedProduits = [];
            foreach ($produits as $p) {
                $prodErrors = [];
                $pResolved = $this->resolveFriendlyReferences($p, $prodErrors);
                if (!empty($prodErrors)) {
                    $errors[] = ['row' => $index + 2, 'type' => 'transfert_produit', 'errors' => $prodErrors];
                    continue 2; // abandonner ce bon
                }

                // Vérifier les champs obligatoires du produit
                $requiredTpFields = ['materiel_id', 'chauffeur_id', 'produit_id', 'lieu_stockage_depart_id', 'lieu_stockage_arrive_id', 'unite_id'];
                $missingTp = [];
                foreach ($requiredTpFields as $field) {
                    if (empty($pResolved[$field])) {
                        $missingTp[] = $field;
                    }
                }
                if (!empty($missingTp)) {
                    $errors[] = ['row' => $index + 2, 'type' => 'transfert_produit', 'errors' => ["Champs obligatoires non résolus : " . implode(', ', $missingTp)]];
                    continue 2;
                }

                // Normaliser les dates/heures
                $pResolved['date'] = $this->normalizeDate($pResolved['date'] ?? null);
                $pResolved['heure_depart'] = $this->normalizeTime($pResolved['heure_depart'] ?? null);
                $pResolved['heure_arrivee'] = $this->normalizeTime($pResolved['heure_arrivee'] ?? null);

                // Convertir les booléens
                $pResolved['isDelivred'] = $this->toBoolean($pResolved['isDelivred'] ?? false);

                $resolvedProduits[] = $pResolved;
            }

            if (empty($resolvedProduits)) {
                $errors[] = ['row' => $index + 2, 'type' => 'bon_transfert', 'errors' => ["Aucun produit valide après résolution."]];
                continue;
            }

            try {
                DB::transaction(function () use ($row, $resolvedProduits, &$btCreated, &$btUpdated, &$tpCreated) {
                    // Vérifier si le bon de transfert existe déjà (par numero_bon unique)
                    $existing = DB::table('bon_transferts')->where('numero_bon', $row['numero_bon'])->first();

                    // Préparer le payload du bon de transfert
                    $btPayload = $this->filterPayloadByTableColumns($row, Schema::getColumnListing('bon_transferts'));

                    // Normaliser la date
                    if (isset($btPayload['date_transfert'])) {
                        $btPayload['date_transfert'] = $this->normalizeDate($btPayload['date_transfert']);
                    }

                    // Booléen
                    if (isset($btPayload['est_utilise'])) {
                        $btPayload['est_utilise'] = $this->toBoolean($btPayload['est_utilise']);
                    }

                    $btPayload['created_at'] = now();
                    $btPayload['updated_at'] = now();

                    if ($existing) {
                        DB::table('bon_transferts')->where('id', $existing->id)->update($btPayload);
                        $bonTransfertId = $existing->id;
                        $btUpdated++;
                    } else {
                        $bonTransfertId = DB::table('bon_transferts')->insertGetId($btPayload);
                        $btCreated++;
                    }

                    // Insérer les produits de transfert
                    foreach ($resolvedProduits as $produit) {
                        $tpPayload = $this->filterPayloadByTableColumns($produit, Schema::getColumnListing('transfert_produits'));
                        $tpPayload['bon_transfert_id'] = $bonTransfertId;
                        $tpPayload['created_at'] = now();
                        $tpPayload['updated_at'] = now();

                        DB::table('transfert_produits')->insert($tpPayload);
                        $tpCreated++;
                    }
                });
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index + 2, 'type' => 'bon_transfert', 'errors' => [$e->getMessage()]];
            }
        }

        return [
            'bon_transfert_created'   => $btCreated,
            'transfert_produit_created' => $tpCreated,
            'errors' => $errors,
        ];
    }

    private function dispatchImport(string $importType, array $headings, array $dataRows, string $flowType): array
    {
        if ($importType === 'materiel') {
            return $this->importMateriels($headings, $dataRows);
        }

        if ($importType === 'fourniture') {
            return $this->importFournituresMonoFeuille($headings, $dataRows);
        }

        if ($importType === 'bon_livraison') {
            return $this->importBonLivraisonMonoFeuille($headings, $dataRows);
        }

        if ($importType === 'pneu') {
            return $this->importPneusMonoFeuille($headings, $dataRows);
        }

        if ($importType === 'produits') {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [
                ['row' => 1, 'errors' => ["La feuille 'produits' doit être importée avec les feuilles 'production_produits' et 'production_materiels'."]],
            ]];
        }

        $config = [
            'atelier_materiel' => ['table' => 'operation_atelier_mecas'],
            'gasoil' => ['table' => 'gasoils'],
            'huile' => ['table' => 'huiles'],
            'pneu' => ['table' => 'pneus'],
            'fourniture' => ['table' => 'fournitures'],
            'production' => ['table' => 'production_produits'],
            'bon_commande' => ['table' => 'bon_commandes', 'unique' => ['numero']],
            'bon_livraison' => ['table' => 'bon_livraisons', 'unique' => ['numBL']],
            'melange' => ['table' => 'melange_produits'],
            'bon_transfert' => ['table' => 'bon_transferts', 'unique' => ['numero_bon']],
            'transfert' => ['table' => 'transfert_produits'],
            'vente' => ['table' => 'ventes'],
            'entrer' => ['table' => 'entrers'],
            'sortie' => ['table' => 'sorties'],
            'stock' => ['table' => 'stocks', 'unique' => ['article_id', 'lieu_stockage_id']],
        ];

        $target = $config[$importType] ?? null;
        if (!$target) {
            return ['created' => 0, 'updated' => 0, 'skipped' => count($dataRows), 'errors' => [['row' => 1, 'errors' => ['import_type' => ['Type non géré.']]]]];
        }

        return $this->importGenericTable($target['table'], $headings, $dataRows, $target['unique'] ?? []);
    }

    private function normalizeSheet(array $sheet): array
    {
        $rawHeadings = $sheet[0] ?? [];
        $headings = array_map(fn($h) => $this->normalizeHeadingKey($h), $rawHeadings);
        $dataRows = array_values(array_slice($sheet, 1));

        if (count($headings) === 1 && is_string($headings[0])) {
            $delimiter = $this->detectDelimiter($headings[0]);
            if ($delimiter !== null) {
                $headings = array_map(fn($h) => $this->normalizeHeadingKey($h), explode($delimiter, $headings[0]));
                $dataRows = array_map(function ($row) use ($delimiter) {
                    if (count($row) === 1 && is_string($row[0])) {
                        return $this->splitDelimitedRow((string)$row[0], $delimiter);
                    }
                    return $row;
                }, $dataRows);
            }
        }

        $headings = array_values(array_filter($headings, fn($h) => $h !== ''));
        return [$headings, $dataRows];
    }

    private function importGenericTable(string $table, array $headings, array $dataRows, array $uniqueColumns = []): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $tableColumns = Schema::getColumnListing($table);
        $now = now();

        foreach ($dataRows as $index => $rowValues) {
            $row = $this->rowToAssoc($headings, $rowValues);
            if ($this->isEmptyRow($row)) {
                $skipped++;
                continue;
            }

            $referenceErrors = [];
            $row = $this->resolveFriendlyReferences($row, $referenceErrors);
            if (!empty($referenceErrors)) {
                $errors[] = [
                    'row' => $index + 2,
                    'errors' => $referenceErrors,
                ];
                continue;
            }

            $payload = $this->filterPayloadByTableColumns($row, $tableColumns);
            $payload = $this->normalizeBooleans($payload);
            $payload = $this->normalizeDatesInPayload($payload, $tableColumns);

            if (in_array('created_at', $tableColumns, true)) {
                $payload['created_at'] = $now;
            }
            if (in_array('updated_at', $tableColumns, true)) {
                $payload['updated_at'] = $now;
            }

            if (empty($payload)) {
                $skipped++;
                continue;
            }

            try {
                if (!empty($uniqueColumns) && $this->hasAllUniqueColumns($payload, $uniqueColumns)) {
                    $where = [];
                    foreach ($uniqueColumns as $key) {
                        $where[$key] = $payload[$key];
                    }
                    $exists = DB::table($table)->where($where)->exists();
                    DB::table($table)->updateOrInsert($where, $payload);
                    $exists ? $updated++ : $created++;
                } else {
                    DB::table($table)->insert($payload);
                    $created++;
                }
            } catch (Throwable $exception) {
                $errors[] = [
                    'row' => $index + 2,
                    'errors' => [$exception->getMessage()],
                ];
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function importMateriels(array $headings, array $dataRows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($dataRows as $index => $rowValues) {
            $row = $this->rowToAssoc($headings, $rowValues);
            if ($this->isEmptyRow($row)) {
                $skipped++;
                continue;
            }

            $validator = Validator::make($row, [
                'nom_materiel' => ['required', 'string', 'max:100'],
                'status' => ['required'],
                'categorie' => ['required', 'in:groupe,vehicule,engin'],
                'nbr_pneu' => ['nullable', 'integer', 'min:4', 'max:22'],
                'capaciteL' => ['nullable', 'numeric', 'min:0'],
                'capaciteCm' => ['nullable', 'numeric', 'min:0'],
                'consommation_horaire' => ['nullable', 'numeric', 'min:0'],
                'compteur_actuel' => ['nullable', 'numeric', 'min:0'],
                'seuil' => ['nullable', 'numeric', 'min:0'],
                'actuelGasoil' => ['nullable', 'numeric', 'min:0'],
            ]);

            if ($validator->fails()) {
                $errors[] = [
                    'row' => $index + 2,
                    'errors' => $validator->errors()->toArray(),
                ];
                continue;
            }

            $payload = $validator->validated();
            $payload['status'] = $this->toBoolean($payload['status'] ?? false);

            $existing = Materiel::where('nom_materiel', $payload['nom_materiel'])->first();
            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Materiel::create($payload);
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function rowToAssoc(array $headings, array $rowValues): array
    {
        $assoc = [];
        foreach ($headings as $i => $key) {
            $value = $rowValues[$i] ?? null;
            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }
            $assoc[$this->normalizeHeadingKey($key)] = $value;
        }
        return $assoc;
    }

    private function filterPayloadByTableColumns(array $row, array $tableColumns): array
    {
        $columnIndex = [];
        foreach ($tableColumns as $column) {
            $columnIndex[$this->normalizeHeadingKey($column)] = $column;
        }

        $payload = [];
        foreach ($row as $key => $value) {
            $normalizedKey = $this->normalizeHeadingKey($key);
            if (isset($columnIndex[$normalizedKey])) {
                $payload[$columnIndex[$normalizedKey]] = $value;
            }
        }

        unset($payload['id']);
        return $payload;
    }

    private function normalizeBooleans(array $payload): array
    {
        foreach (['status', 'isDelivred', 'isAtelierMeca', 'is_remis', 'seuil_notif', 'is_consumed'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null) {
                $payload[$key] = $this->toBoolean($payload[$key]);
            }
        }
        return $payload;
    }

    private function normalizeDatesInPayload(array $payload, array $tableColumns): array
    {
        $dateColumns = [
            'date_prod',
            'date_BC',
            'date_elaboration',
            'date_prevu_livraison',
            'date_livraison',
            'date_arriver',
            'date_operation',
            'date',
            'sortie',
            'date_consommation',
            'entre'
        ];

        $timeColumns = ['heure_debut', 'heure_fin', 'heure_depart', 'heure_arrive', 'heure_chauffeur', 'heure_machine'];

        foreach ($payload as $key => $value) {
            if (in_array($key, $dateColumns) && in_array($key, $tableColumns) && $value !== null) {
                $payload[$key] = $this->normalizeDate($value);
            }
            if (in_array($key, $timeColumns) && in_array($key, $tableColumns) && $value !== null) {
                $payload[$key] = $this->normalizeTime($value);
            }
        }

        return $payload;
    }

    private function normalizeDate(mixed $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        if (is_numeric($date)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $date)->format('Y-m-d');
            } catch (\Throwable $e) {
                \Log::warning('Format de date Excel non supporte', [
                    'date_input' => $date,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        }

        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return $date;
            }

            $parsed = \Carbon\Carbon::parse($date);
            return $parsed->format('Y-m-d');
        } catch (\Exception $e) {
            if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $date, $matches)) {
                $month = (int) $matches[1];
                $day   = (int) $matches[2];
                $year  = (int) $matches[3];
                if (checkdate($month, $day, $year)) {
                    return sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }

            \Log::warning('Format de date non supporte', [
                'date_input' => $date,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function normalizeTime(?string $time): ?string
    {
        if (empty($time)) {
            return null;
        }

        try {
            if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
                return strlen($time) === 5 ? $time . ':00' : $time;
            }

            $parsed = \Carbon\Carbon::parse($time);
            return $parsed->format('H:i:s');
        } catch (\Exception $e) {
            \Log::warning('Format d\'heure invalide', [
                'time_input' => $time,
                'error' => $e->getMessage()
            ]);
            return $time;
        }
    }

    /**
     * Résoudre les références amicales (noms → IDs) SAUF pour certaines clés exclues
     * Utile pour bon_commande_produit_id qui est géré via mapping temporaire
     */
    private function resolveFriendlyReferencesExcept(array $row, array &$errors, array $excludeKeys = []): array
    {
        $mappings = [
            ['id' => 'client_id', 'name' => 'client_nom', 'table' => 'clients', 'column' => 'nom_client'],
            ['id' => 'destination_id', 'name' => 'destination_nom', 'table' => 'destinations', 'column' => 'nom_destination'],
            ['id' => 'article_id', 'name' => 'article_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'article_id', 'name' => 'article_versement_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'produit_a_id', 'name' => 'produit_a_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'produit_b_id', 'name' => 'produit_b_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'article_versement_id', 'name' => 'article_versement_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'produit_id', 'name' => 'produit_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'categorie_article_id', 'name' => 'categorie_article_nom', 'table' => 'categorie_articles', 'column' => 'nom_categorie'],
            ['id' => 'lieu_stockage_id', 'name' => 'lieu_stockage_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'source_lieu_stockage_id', 'name' => 'source_lieu_stockage_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'lieu_stockage_depart_id', 'name' => 'lieu_stockage_depart_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'lieu_stockage_arrive_id', 'name' => 'lieu_stockage_arrive_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'lieu_stockage_a_id', 'name' => 'lieu_stockage_a_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'lieu_stockage_b_id', 'name' => 'lieu_stockage_b_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'lieu_stockage_final_id', 'name' => 'lieu_stockage_final_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'unite_id', 'name' => 'unite_nom', 'table' => 'unites', 'column' => 'nom_unite'],
            ['id' => 'unite_livraison_id', 'name' => 'unite_livraison_nom', 'table' => 'unites', 'column' => 'nom_unite'],
            ['id' => 'materiel_id', 'name' => 'materiel_nom', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'materiel_id_cible', 'name' => 'materiel_nom_cible', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'materiel_id_source', 'name' => 'materiel_nom_source', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'vehicule_id', 'name' => 'vehicule_nom', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'chauffeur_id', 'name' => 'chauffeur_nom', 'table' => 'conducteurs', 'column' => 'nom_conducteur'],
            ['id' => 'aide_chauffeur_id', 'name' => 'aide_chauffeur_nom', 'table' => 'aide_chauffeurs', 'column' => 'nom_aideChauffeur'],
            ['id' => 'bon_transfert_id', 'name' => 'bon_transfert_numero', 'table' => 'bon_transferts', 'column' => 'numero_bon'],
            ['id' => 'categorie_travail_id', 'name' => 'categorie_travail_nom', 'table' => 'categories', 'column' => 'nom_categorie'],
            ['id' => 'bon_gasoil_id', 'name' => 'num_bon', 'table' => 'bon_gasoils', 'column' => 'num_bon'],
            ['id' => 'bon_huile_id', 'name' => 'num_bon', 'table' => 'bon_huiles', 'column' => 'num_bon'],
            ['id' => 'subdivision_id_cible', 'name' => 'subdivision_nom_cible', 'table' => 'subdivisions', 'column' => 'nom_subdivision'],
            ['id' => 'subdivision_id_source', 'name' => 'subdivision_nom_source', 'table' => 'subdivisions', 'column' => 'nom_subdivision'],
            ['id' => 'user_id', 'name' => 'user_nom', 'table' => 'users', 'column' => 'nom'],
            ['id' => 'ancien_materiel_id', 'name' => 'ancien_materiel_nom', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'nouveau_materiel_id', 'name' => 'nouveau_materiel_nom', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'materiel_id_associe', 'name' => 'materiel_nom_associe', 'table' => 'materiels', 'column' => 'nom_materiel'],
            // ⚠️ bon_commande_produit_id est EXCLU de cette liste car géré séparément via mapping
        ];

        foreach ($mappings as $map) {
            $idKey = $map['id'];
            $nameKey = $map['name'];

            if (in_array($nameKey, $excludeKeys, true)) {
                continue;
            }

            // Skip if this key is in the exclude list
            if (in_array($idKey, $excludeKeys, true)) {
                continue;
            }

            $nameKey = $map['name'];
            if ((!isset($row[$idKey]) || $row[$idKey] === null || $row[$idKey] === '')
                && isset($row[$nameKey]) && $row[$nameKey] !== ''
            ) {
                $resolvedId = $this->resolveReferenceId($map['table'], $map['column'], (string) $row[$nameKey]);
                if ($resolvedId) {
                    $row[$idKey] = $resolvedId;
                } else {
                    $errors[$nameKey] = ["Valeur introuvable: '{$row[$nameKey]}' ({$map['table']}.{$map['column']})."];
                }
            }
        }

        return $row;
    }

    private function resolveFriendlyReferences(array $row, array &$errors): array
    {
        $mappings = [
            ['id' => 'client_id', 'name' => 'client_nom', 'table' => 'clients', 'column' => 'nom_client'],
            ['id' => 'destination_id', 'name' => 'destination_nom', 'table' => 'destinations', 'column' => 'nom_destination'],
            ['id' => 'article_id', 'name' => 'article_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'produit_a_id', 'name' => 'produit_a_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'produit_b_id', 'name' => 'produit_b_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'article_id', 'name' => 'article_versement_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'article_versement_id', 'name' => 'article_versement_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'produit_id', 'name' => 'produit_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'categorie_article_id', 'name' => 'categorie_article_nom', 'table' => 'categorie_articles', 'column' => 'nom_categorie'],
            ['id' => 'lieu_stockage_id', 'name' => 'lieu_stockage_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'lieu_stockage_a_id', 'name' => 'lieu_stockage_a_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'lieu_stockage_b_id', 'name' => 'lieu_stockage_b_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'lieu_stockage_final_id', 'name' => 'lieu_stockage_final_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'source_lieu_stockage_id', 'name' => 'source_lieu_stockage_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'lieu_stockage_depart_id', 'name' => 'lieu_stockage_depart_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'lieu_stockage_arrive_id', 'name' => 'lieu_stockage_arrive_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'unite_id', 'name' => 'unite_nom', 'table' => 'unites', 'column' => 'nom_unite'],
            ['id' => 'unite_livraison_id', 'name' => 'unite_livraison_nom', 'table' => 'unites', 'column' => 'nom_unite'],
            ['id' => 'materiel_id', 'name' => 'materiel_nom', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'materiel_id_cible', 'name' => 'materiel_nom_cible', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'materiel_id_source', 'name' => 'materiel_nom_source', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'vehicule_id', 'name' => 'vehicule_nom', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'chauffeur_id', 'name' => 'chauffeur_nom', 'table' => 'conducteurs', 'column' => 'nom_conducteur'],
            ['id' => 'aide_chauffeur_id', 'name' => 'aide_chauffeur_nom', 'table' => 'aide_chauffeurs', 'column' => 'nom_aideChauffeur'],
            ['id' => 'bon_transfert_id', 'name' => 'bon_transfert_numero', 'table' => 'bon_transferts', 'column' => 'numero_bon'],
            ['id' => 'categorie_travail_id', 'name' => 'categorie_travail_nom', 'table' => 'categories', 'column' => 'nom_categorie'],
            ['id' => 'bon_gasoil_id', 'name' => 'num_bon', 'table' => 'bon_gasoils', 'column' => 'num_bon'],
            ['id' => 'bon_huile_id', 'name' => 'num_bon', 'table' => 'bon_huiles', 'column' => 'num_bon'],
            ['id' => 'subdivision_id_cible', 'name' => 'subdivision_nom_cible', 'table' => 'subdivisions', 'column' => 'nom_subdivision'],
            ['id' => 'subdivision_id_source', 'name' => 'subdivision_nom_source', 'table' => 'subdivisions', 'column' => 'nom_subdivision'],
            ['id' => 'user_id', 'name' => 'user_nom', 'table' => 'users', 'column' => 'nom'],
            ['id' => 'ancien_materiel_id', 'name' => 'ancien_materiel_nom', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'nouveau_materiel_id', 'name' => 'nouveau_materiel_nom', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'materiel_id_associe', 'name' => 'materiel_nom_associe', 'table' => 'materiels', 'column' => 'nom_materiel'],
            // ⚠️ AJOUTÉ POUR BON LIVRAISON
            ['id' => 'bon_commande_produit_id', 'name' => 'bon_commande_produit_ref', 'table' => 'bon_commande_produits', 'column' => 'id'],
        ];

        foreach ($mappings as $map) {
            $idKey = $map['id'];
            $nameKey = $map['name'];

            if ((!isset($row[$idKey]) || $row[$idKey] === null || $row[$idKey] === '')
                && isset($row[$nameKey]) && $row[$nameKey] !== ''
            ) {

                $resolvedId = $this->resolveReferenceId($map['table'], $map['column'], (string) $row[$nameKey]);

                if ($resolvedId) {
                    $row[$idKey] = $resolvedId;
                } else {
                    $errors[$nameKey] = ["Valeur introuvable: '{$row[$nameKey]}' ({$map['table']}.{$map['column']})."];
                }
            }
        }
        return $row;
    }

    private function detectDelimiter(string $line): ?string
    {
        if (str_contains($line, "	")) {
            return "	";
        }
        if (str_contains($line, ';')) {
            return ';';
        }
        if (str_contains($line, ',')) {
            return ',';
        }
        return null;
    }

    private function normalizeHeadingKey(mixed $value): string
    {
        $key = is_string($value) ? $value : (string)$value;
        $key = trim($key);
        $key = preg_replace('/^\x{FEFF}/u', '', $key) ?? $key;
        $key = str_replace([' ', '-', '.'], '_', $key);
        $key = preg_replace('/_+/', '_', $key) ?? $key;
        return strtolower(trim($key, '_'));
    }

    private function splitDelimitedRow(string $raw, string $delimiter): array
    {
        $parts = array_map('trim', explode($delimiter, $raw));
        return array_map(fn($v) => preg_replace('/^﻿/u', '', $v) ?? $v, $parts);
    }

    private function hasAllUniqueColumns(array $payload, array $uniqueColumns): bool
    {
        foreach ($uniqueColumns as $key) {
            if (!array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
                return false;
            }
        }
        return true;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }
        return true;
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string)$value), ['1', 'true', 'oui', 'yes'], true);
    }

    private function validateBusinessRules(string $importType, string $flowType, bool $autoValidate): void
    {
        $requiresSpecificFlow = in_array($importType, ['gasoil', 'huile'], true);
        if ($requiresSpecificFlow && !in_array($flowType, ['bon', 'stock'], true)) {
            throw ValidationException::withMessages([
                'flow_type' => 'Pour gasoil/huile, le type d\'approvisionnement doit être "bon" ou "stock".',
            ]);
        }

        if (!$requiresSpecificFlow && $flowType !== 'standard') {
            throw ValidationException::withMessages([
                'flow_type' => 'Le type d\'approvisionnement "bon/stock" est réservé aux imports gasoil/huile.',
            ]);
        }

        $supportsAutoValidation = in_array($importType, self::AUTO_VALIDATE_IMPORT_TYPES, true);
        if ($autoValidate && !$supportsAutoValidation) {
            throw ValidationException::withMessages([
                'auto_validate' => 'La validation automatique est disponible uniquement pour les imports bon de livraison et transfert.',
            ]);
        }
    }

    private function resolveSheetImportType(?string $sheetName, ?string $fallbackImportType): ?string
    {
        $normalized = $sheetName !== null ? $this->normalizeHeadingKey($sheetName) : null;

        // Mapping pour les noms de feuilles au pluriel
        $pluralMap = [
            'bon_transferts' => 'bon_transfert',
            'transfert_produits' => 'transfert',
        ];

        if ($normalized !== null && isset($pluralMap[$normalized])) {
            return $pluralMap[$normalized];
        }

        if ($normalized !== null && in_array($normalized, self::ALLOWED_IMPORT_TYPES, true)) {
            return $normalized;
        }

        return $fallbackImportType;
    }

    private function getSheetNames(\Illuminate\Http\UploadedFile $file): array
    {
        try {
            $spreadsheet = $this->loadSpreadsheet($file->getRealPath());
            return $spreadsheet->getSheetNames();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function readSheets(\Illuminate\Http\UploadedFile $file): array
    {
        $spreadsheet = $this->loadSpreadsheet($file->getRealPath());
        $sheetNames  = $spreadsheet->getSheetNames();
        $rows        = [];

        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $sheetRows = [];
            $consecutiveEmptyRows = 0;
            $maxConsecutiveEmptyRows = 100;
            $highestDataColumn = $worksheet->getHighestDataColumn();

            if (empty($highestDataColumn)) {
                $rows[] = [];
                continue;
            }

            foreach ($worksheet->getRowIterator() as $row) {
                $rowIndex = $row->getRowIndex();
                $cellValues = $worksheet->rangeToArray(
                    "A{$rowIndex}:{$highestDataColumn}{$rowIndex}",
                    null,
                    true,
                    false,
                    false
                )[0] ?? [];

                $sheetRows[] = $cellValues;

                if ($this->isEntireRowEmpty($cellValues)) {
                    $consecutiveEmptyRows++;
                    if (!empty($sheetRows) && $consecutiveEmptyRows >= $maxConsecutiveEmptyRows) {
                        break;
                    }
                    continue;
                }

                $consecutiveEmptyRows = 0;
            }

            $rows[] = $this->trimTrailingEmptyRows($sheetRows);
        }

        return [$rows, $sheetNames];
    }

    private function loadSpreadsheet(string $path): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        return $reader->load($path);
    }

    private function isEntireRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function trimTrailingEmptyRows(array $rows): array
    {
        while (!empty($rows) && $this->isEntireRowEmpty($rows[array_key_last($rows)])) {
            array_pop($rows);
        }

        return $rows;
    }

    private function resolveReferenceId(string $table, string $column, string $value): mixed
    {
        $resolvedId = DB::table($table)
            ->where($column, $value)
            ->value('id');

        if ($resolvedId) {
            return $resolvedId;
        }

        $normalizedValue = $this->normalizeLookupValue($value);
        if ($normalizedValue === '') {
            return null;
        }

        $candidates = DB::table($table)->select('id', $column)->get();
        $fuzzyMatches = [];

        foreach ($candidates as $candidate) {
            $candidateValue = (string) ($candidate->{$column} ?? '');
            $normalizedCandidate = $this->normalizeLookupValue($candidateValue);

            if ($normalizedCandidate === '') {
                continue;
            }

            if ($normalizedCandidate === $normalizedValue) {
                return $candidate->id;
            }

            if (
                str_starts_with($normalizedCandidate, $normalizedValue)
                || str_starts_with($normalizedValue, $normalizedCandidate)
            ) {
                $fuzzyMatches[$candidate->id] = true;
            }
        }

        return count($fuzzyMatches) === 1 ? array_key_first($fuzzyMatches) : null;
    }

    private function normalizeLookupValue(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $text = \Illuminate\Support\Str::ascii($text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '', $text) ?? $text;

        return $text;
    }

    private function normalizePneuImportRow(array $row): array
    {
        if (isset($row['etat'])) {
            $row['etat'] = $this->normalizePneuEtat($row['etat']) ?? $row['etat'];
        }

        if (isset($row['situation'])) {
            $row['situation'] = $this->normalizePneuSituation($row['situation']) ?? $row['situation'];
        }

        if (!empty($row['materiel_nom'])) {
            $row['materiel_nom'] = $this->normalizeMaterielAlias($row['materiel_nom']);
        }

        if (empty($row['lieu_stockage_id']) && !empty($row['lieu_stockage_nom'])) {
            $row['lieu_stockage_id'] = $this->resolveReferenceId('lieu_stockages', 'nom', (string) $row['lieu_stockage_nom']);
        }

        if (empty($row['lieu_stockages_id']) && !empty($row['lieu_stockages_nom'])) {
            $row['lieu_stockages_id'] = $this->resolveReferenceId('lieu_stockages', 'nom', (string) $row['lieu_stockages_nom']);
        }

        if (!isset($row['kilometrage']) || $row['kilometrage'] === null || $row['kilometrage'] === '') {
            $row['kilometrage'] = 0;
        }

        return $row;
    }

    private function normalizeHistoriquePneuImportRow(array $row): array
    {
        if (!empty($row['ancien_materiel_nom'])) {
            $row['ancien_materiel_nom'] = $this->normalizeMaterielAlias($row['ancien_materiel_nom']);
        }

        if (!empty($row['nouveau_materiel_nom'])) {
            $row['nouveau_materiel_nom'] = $this->normalizeMaterielAlias($row['nouveau_materiel_nom']);
        }

        if (isset($row['etat'])) {
            $normalizedEtat = $this->normalizePneuEtat($row['etat']);
            if ($normalizedEtat !== null) {
                $row['etat'] = $normalizedEtat;
            } else {
                unset($row['etat']);
            }
        }

        if (isset($row['type_action'])) {
            $normalizedAction = $this->normalizeHistoriquePneuAction($row['type_action']);
            if ($normalizedAction === null) {
                $row['__skip_history'] = true;
            } else {
                $row['type_action'] = $normalizedAction;
            }
        }

        return $row;
    }

    private function normalizePneuEtat(mixed $value): ?string
    {
        return match ($this->normalizeLookupValue($value)) {
            'bonne' => 'bonne',
            'usee' => "us\u{00E9}e",
            'endommagee' => "endommag\u{00E9}e",
            'defectueuse' => "d\u{00E9}fectueuse",
            default => null,
        };
    }

    private function normalizePneuSituation(mixed $value): ?string
    {
        return match ($this->normalizeLookupValue($value)) {
            'enservice' => 'en_service',
            'enstock' => 'en_stock',
            'enreparation' => 'en_reparation',
            'horsservice' => 'hors_service',
            default => null,
        };
    }

    private function normalizeHistoriquePneuAction(mixed $value): ?string
    {
        return match ($this->normalizeLookupValue($value)) {
            'ajout' => 'ajout',
            'transfert' => 'transfert',
            'retrait' => 'retrait',
            'misehorsservice' => 'mise_hors_service',
            'reparation' => 'reparation',
            'echange' => 'transfert',
            'aucunmouvement' => null,
            default => null,
        };
    }

    private function normalizeMaterielAlias(mixed $value): string
    {
        $original = trim((string) $value);

        return match ($this->normalizeLookupValue($original)) {
            'man1' => 'MAN 1',
            'man2' => 'MAN 2',
            'howo1' => 'HOWO 1',
            'howo2' => 'HOWO 2',
            'ge1olympian' => 'GE1 Olympian',
            'ge2vert' => 'GE2 VERT',
            'groupeautonome' => 'GROUPE AUTONOME',
            'groupekubota' => 'GROUPE KUBOTA',
            'groupetotal' => 'GROUPE TOTAL',
            'ateliermecanique' => 'ATELIER MECA',
            default => $original,
        };
    }
    private function normalizeDateString(string $value): string
    {
        try {
            if (preg_match('/[\d]{1,4}[\/-][\d]{1,2}[\/-][\d]{2,4}/', $value)) {
                return Carbon::parse($value)->format('Y-m-d');
            }
        } catch (\Throwable $e) {
            // Pas une date, retourner la valeur originale
        }
        return $value;
    }

    private function buildMultiSheetMessage(int $created, int $updated, int $errors, array $unknownSheets): string
    {
        $parts = [];
        if ($created > 0 || $updated > 0) {
            $parts[] = "{$created} créé(s), {$updated} mis à jour";
        }
        if ($errors > 0) {
            $parts[] = "{$errors} erreur(s)";
        }
        if (!empty($unknownSheets)) {
            $list    = implode(', ', array_map(fn($s) => "[{$s}]", $unknownSheets));
            $parts[] = "feuilles non reconnues : {$list}";
        }
        if (empty($parts)) {
            return 'Aucune donnée importée.';
        }
        return 'Import multi-feuilles terminé — ' . implode(' | ', $parts) . '.';
    }

    private function menuTemplates(): array
    {
        return [
            ['key' => 'materiel', 'label' => 'Matériel', 'source' => 'materiels', 'required_fields' => ['nom_materiel', 'status', 'categorie']],
            ['key' => 'atelier_materiel', 'label' => 'Atelier matériel', 'source' => 'operation_atelier_mecas', 'required_fields' => ['materiel_id', 'gasoil_retirer', 'quantite_retiree_cm', 'reste_gasoil']],
            ['key' => 'gasoil', 'label' => 'Gasoil', 'source' => 'gasoils', 'required_fields' => ['type_operation', 'quantite']],
            ['id' => 'user_id', 'name' => 'user_nom', 'table' => 'users', 'column' => 'nom'],
            [
                'key' => 'gasoil_multi',
                'label' => 'Gasoil (Multi-feuilles)',
                'source' => 'bon_gasoils',
                'required_fields' => ['num_bon', 'quantite', 'materiel_nom_cible'],
                'multi_sheet' => true,
                'sheets' => ['bon_gasoils', 'gasoils']
            ],
            ['key' => 'huile', 'label' => 'Huile', 'source' => 'huiles', 'required_fields' => ['type_operation', 'quantite']],
            [
                'key' => 'huile_multi',
                'label' => 'Huile (Multi-feuilles)',
                'source' => 'bon_huiles',
                'required_fields' => ['num_bon', 'quantite', 'materiel_nom_cible', 'article_versement_nom'],
                'multi_sheet' => true,
                'sheets' => ['bon_huiles', 'huiles']
            ],
            ['key' => 'pneu', 'label' => 'Pneu', 'source' => 'pneus', 'required_fields' => ['reference_pneu', 'marque', 'dimension']],
            ['key' => 'fourniture', 'label' => 'Fourniture', 'source' => 'fournitures', 'required_fields' => ['nom_fourniture']],
            ['key' => 'production', 'label' => 'Production', 'source' => 'production_produits', 'required_fields' => ['production_id', 'produit_id', 'quantite', 'unite_id']],
            ['key' => 'melange', 'label' => 'Mélange', 'source' => 'melange_produits', 'required_fields' => ['date', 'produit_a_id', 'produit_b_id', 'produit_b_final_id']],
            ['key' => 'bon_transfert', 'label' => 'Bon de transfert', 'source' => 'bon_transferts', 'required_fields' => ['numero_bon', 'date', 'produit_id', 'quantite']],
            ['key' => 'transfert', 'label' => 'Transfert', 'source' => 'transfert_produits', 'required_fields' => ['date', 'materiel_id', 'produit_id', 'quantite']],
            ['key' => 'vente', 'label' => 'Vente', 'source' => 'ventes', 'required_fields' => ['date', 'client_id', 'materiel_id', 'bl_id', 'produit_id', 'quantite']],
            ['key' => 'entrer', 'label' => 'Entrée', 'source' => 'entrers', 'required_fields' => ['user_name', 'article_id', 'categorie_article_id', 'lieu_stockage_id', 'unite_id', 'quantite']],
            ['key' => 'sortie', 'label' => 'Sortie', 'source' => 'sorties', 'required_fields' => ['user_name', 'article_id', 'categorie_article_id', 'lieu_stockage_id', 'unite_id', 'quantite']],
            ['key' => 'stock', 'label' => 'Stock', 'source' => 'stocks', 'required_fields' => ['article_id', 'categorie_article_id', 'lieu_stockage_id', 'quantite']],

            [
                'key' => 'bon_commande_livraison',
                'label' => 'Bon de commande + Livraison',
                'source' => 'bon_commandes',
                'required_fields' => [
                    'numero',
                    'date_BC',
                    'client_nom',
                    'article_nom',
                    'lieu_stockage_nom',
                    'quantite',
                    'numBL',
                    'vehicule_nom',
                    'chauffeur_nom'
                ],
                'multi_sheet' => true,
                'sheets' => ['bon_commandes', 'bon_commande_produits', 'bon_livraisons']
            ],
            // Garder BC seul pour les cas où on veut juste importer des BC
            [
                'key' => 'bon_commande',
                'label' => 'Bon de commande (seulement)',
                'source' => 'bon_commandes',
                'required_fields' => ['numero', 'date_BC', 'client_nom', 'article_nom', 'lieu_stockage_nom', 'quantite'],
                'multi_sheet' => true,
                'sheets' => ['bon_commandes', 'bon_commande_produits']
            ],

            [
                'key' => 'pneu_multi',
                'label' => 'Pneus + Historique',
                'source' => 'pneus',
                'required_fields' => [
                    'num_serie',
                    'date_obtention',
                    'etat',
                    'situation',
                    'caracteristiques',
                    'marque',
                    'type',
                    'kilometrage',
                ],
                'multi_sheet' => true,
                'sheets' => ['pneus', 'historique_pneus']
            ],

            [
                'key' => 'fourniture',
                'label' => 'Fourniture (seule)',
                'source' => 'fournitures',
                'required_fields' => ['nom_article', 'reference', 'etat', 'date_acquisition'],
            ],
            [
                'key' => 'fourniture_multi',
                'label' => 'Fournitures + Historique',
                'source' => 'fournitures',
                'required_fields' => [
                    'nom_article',
                    'reference',
                    'etat',
                    'date_acquisition',
                ],
                'multi_sheet' => true,
                'sheets' => ['fournitures', 'historique_fournitures']
            ],

            [
                'key' => 'transfert_multi',
                'label' => 'Transfert (Multi-feuilles)',
                'source' => 'bon_transferts',
                'required_fields' => [
                    'numero_bon',
                    'date_transfert',
                    'produit_nom',
                    'quantite',
                    'unite_nom',
                    'lieu_stockage_depart_nom',
                    'lieu_stockage_arrive_nom',
                    'user_nom',
                    'materiel_nom',
                    'chauffeur_nom',
                    'heure_depart',
                    'gasoil_depart',
                ],
                'multi_sheet' => true,
                'sheets' => ['bon_transferts', 'transfert_produits']
            ],
        ];
    }
}
