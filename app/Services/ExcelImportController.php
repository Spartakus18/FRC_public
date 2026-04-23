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
use App\Services\EntrerService;
use App\Services\SortieService;
use App\Services\BonCommandeService;
use App\Services\GasoilService;
use App\Services\HuileService;
use App\Services\BonLivraisonService;
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
     * Feuilles attendues : "produits", "production_produits", "production_materiels".
     * L'id dans la feuille "produits" est un identifiant temporaire local au fichier Excel
     * utilisé pour lier les lignes des autres feuilles à la bonne production.
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
                'created' => 0, 'updated' => 0, 'skipped' => 0,
                'errors'  => [['row' => 0, 'errors' => ['Feuilles manquantes : ' . implode(', ', $missing)]]],
            ];
        }

        [$prodHeadings,  $prodRows]     = $this->normalizeSheet($sheetsByName['produits']);
        [$ppHeadings,    $ppRows]       = $this->normalizeSheet($sheetsByName['production_produits']);
        [$pmHeadings,    $pmRows]       = $this->normalizeSheet($sheetsByName['production_materiels']);

        // Indexer production_produits et production_materiels par production_id temporaire
        $produitsByTempId   = [];
        foreach ($ppRows as $row) {
            $assoc = $this->rowToAssoc($ppHeadings, $row);
            if ($this->isEmptyRow($assoc)) continue;
            $tempId = (int) ($assoc['production_id'] ?? 0);
            if ($tempId <= 0) continue; // Ignorer les lignes sans production_id valide (notes, lignes vides)
            $produitsByTempId[$tempId][] = $assoc;
        }

        $materielsByTempId = [];
        foreach ($pmRows as $row) {
            $assoc = $this->rowToAssoc($pmHeadings, $row);
            if ($this->isEmptyRow($assoc)) continue;
            $tempId = (int) ($assoc['production_id'] ?? 0);
            if ($tempId <= 0) continue; // Ignorer les lignes sans production_id valide (notes, lignes vides)
            $materielsByTempId[$tempId][] = $assoc;
        }

        $service = app(ProductionService::class);
        $created = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($prodRows as $index => $rowValues) {
            $row = $this->rowToAssoc($prodHeadings, $rowValues);
            if ($this->isEmptyRow($row)) { $skipped++; continue; }

            $tempId   = (int) ($row['id'] ?? 0);
            $produits = $produitsByTempId[$tempId] ?? [];
            $materiels = $materielsByTempId[$tempId] ?? [];

            // Résolution des FK friendly pour chaque produit
            $resolvedProduits = [];
            foreach ($produits as $p) {
                $refErrors = [];
                $p = $this->resolveFriendlyReferences($p, $refErrors);
                if (!empty($refErrors)) {
                    $errors[] = ['row' => $index + 2, 'errors' => $refErrors];
                    continue 2; // Sauter toute la production si une FK est invalide
                }
                $resolvedProduits[] = $p;
            }

            // Résolution des FK friendly pour chaque matériel
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
                $errors[] = ['row' => $index + 2, 'errors' => ["Production id temporaire {$tempId} : aucun matériel trouvé dans la feuille production_materiels."]];
                continue;
            }

            try {
                $service->createProduction([
                    'date_prod'      => $row['date_prod'] ?? null,
                    'heure_debut'    => $row['heure_debut'] ?? null,
                    'heure_fin'      => $row['heure_fin'] ?? null,
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

    private function dispatchImport(string $importType, array $headings, array $dataRows, string $flowType): array
    {
        if ($importType === 'materiel') {
            return $this->importMateriels($headings, $dataRows);
        }

        // La production est gérée séparément via importProduction() — jamais via dispatchImport
        if ($importType === 'produits') {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [
                ['row' => 1, 'errors' => ["La feuille 'produits' doit être importée avec les feuilles 'production_produits' et 'production_materiels' dans le même fichier."]],
            ]];
        }

        // Types avec logique métier complexe → services dédiés
        if ($importType === 'entrer') {
            return $this->importEntrer($headings, $dataRows);
        }
        if ($importType === 'sortie') {
            return $this->importSortie($headings, $dataRows);
        }
        if ($importType === 'bon_commande') {
            return $this->importBonCommande($headings, $dataRows);
        }
        if ($importType === 'gasoil') {
            return $this->importGasoil($headings, $dataRows);
        }
        if ($importType === 'huile') {
            return $this->importHuile($headings, $dataRows);
        }
        if ($importType === 'bon_livraison') {
            return $this->importBonLivraison($headings, $dataRows);
        }

        $config = [
            'atelier_materiel' => ['table' => 'operation_atelier_mecas'],
            'pneu'             => ['table' => 'pneus'],
            'fourniture'       => ['table' => 'fournitures'],
            'melange'          => ['table' => 'melange_produits'],
            'bon_transfert'    => ['table' => 'bon_transferts',   'unique' => ['numero_bon']],
            'transfert'        => ['table' => 'transfert_produits'],
            'vente'            => ['table' => 'ventes'],
            'stock'            => ['table' => 'stocks',           'unique' => ['article_id', 'lieu_stockage_id']],
        ];

        $target = $config[$importType] ?? null;
        if (!$target) {
            return ['created' => 0, 'updated' => 0, 'skipped' => count($dataRows), 'errors' => [['row' => 1, 'errors' => ['import_type' => ['Type non géré.']]]]];
        }

        return $this->importGenericTable($target['table'], $headings, $dataRows, $target['unique'] ?? []);
    }

    /**
     * @return array{0:array<int,string>,1:array<int,array<int,mixed>>}
     */
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

            if (in_array('created_at', $tableColumns, true)) {
                $payload['created_at'] = $now;
            }
            if (in_array('updated_at', $tableColumns, true)) {
                $payload['updated_at'] = $now;
            }

            // Exclure les timestamps qui ne sont pas des colonnes métier
            $metaColumns = ['created_at', 'updated_at', 'deleted_at', 'id'];
            $businessColumns = array_diff(array_keys($payload), $metaColumns);
            if (empty($payload) || empty($businessColumns)) {
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
        foreach (['status', 'isDelivred', 'isAtelierMeca', 'is_remis', 'seuil_notif'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null) {
                $payload[$key] = $this->toBoolean($payload[$key]);
            }
        }

        return $payload;
    }


    private function resolveFriendlyReferences(array $row, array &$errors): array
    {
        $mappings = [
            ['id' => 'client_id', 'name' => 'client_nom', 'table' => 'clients', 'column' => 'nom_client'],
            ['id' => 'destination_id', 'name' => 'destination_nom', 'table' => 'destinations', 'column' => 'nom_destination'],
            ['id' => 'article_id', 'name' => 'article_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'produit_id', 'name' => 'produit_nom', 'table' => 'article_depots', 'column' => 'nom_article'],
            ['id' => 'categorie_article_id', 'name' => 'categorie_article_nom', 'table' => 'categorie_articles', 'column' => 'nom_categorie'],
            ['id' => 'lieu_stockage_id', 'name' => 'lieu_stockage_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'lieu_stockage_depart_id', 'name' => 'lieu_stockage_depart_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'lieu_stockage_arrive_id', 'name' => 'lieu_stockage_arrive_nom', 'table' => 'lieu_stockages', 'column' => 'nom'],
            ['id' => 'unite_id', 'name' => 'unite_nom', 'table' => 'unites', 'column' => 'nom_unite'],
            ['id' => 'materiel_id', 'name' => 'materiel_nom', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'vehicule_id', 'name' => 'vehicule_nom', 'table' => 'materiels', 'column' => 'nom_materiel'],
            ['id' => 'chauffeur_id', 'name' => 'chauffeur_nom', 'table' => 'conducteurs', 'column' => 'nom_conducteur'],
            ['id' => 'aide_chauffeur_id', 'name' => 'aide_chauffeur_nom', 'table' => 'aide_chauffeurs', 'column' => 'nom_aideChauffeur'],
            ['id' => 'bon_transfert_id', 'name' => 'bon_transfert_numero', 'table' => 'bon_transferts', 'column' => 'numero_bon'],
            ['id' => 'categorie_travail_id', 'name' => 'categorie_travail_nom', 'table' => 'categories', 'column' => 'nom_categorie'],
        ];

        foreach ($mappings as $map) {
            $idKey = $map['id'];
            $nameKey = $map['name'];

            if ((!isset($row[$idKey]) || $row[$idKey] === null || $row[$idKey] === '') && isset($row[$nameKey]) && $row[$nameKey] !== '') {
                $resolvedId = DB::table($map['table'])->where($map['column'], $row[$nameKey])->value('id');
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
        $key = preg_replace('/^ï»¿/u', '', $key) ?? $key; // UTF-8 BOM
        $key = str_replace([' ', '-', '.'], '_', $key);
        $key = preg_replace('/_+/', '_', $key) ?? $key;
        return strtolower(trim($key, '_'));
    }

    /**
     * @return array<int, string>
     */
    private function splitDelimitedRow(string $raw, string $delimiter): array
    {
        $parts = array_map('trim', explode($delimiter, $raw));
        return array_map(fn($v) => preg_replace('/^ï»¿/u', '', $v) ?? $v, $parts);
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
        $nonEmpty = 0;
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                $nonEmpty++;
                if ($nonEmpty >= 2) {
                    return false;
                }
            }
        }
        // Ligne vide ou ligne avec une seule cellule (notes, titres parasites)
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

    /**
     * Résout le type d'import depuis le nom de feuille.
     * En mono-feuille sans nom reconnu, on retombe sur l'import_type du formulaire.
     */
    private function resolveSheetImportType(?string $sheetName, ?string $fallbackImportType): ?string
    {
        $normalized = $sheetName !== null ? $this->normalizeHeadingKey($sheetName) : null;

        if ($normalized !== null && in_array($normalized, self::ALLOWED_IMPORT_TYPES, true)) {
            return $normalized;
        }

        return $fallbackImportType;
    }

    /**
     * Récupère les noms des feuilles du fichier Excel via PhpSpreadsheet.
     * @return array<int, string>
     */
    private function getSheetNames(\Illuminate\Http\UploadedFile $file): array
    {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            return $spreadsheet->getSheetNames();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Lit toutes les feuilles du fichier via PhpSpreadsheet en forcant les dates
     * a rester sous forme de string (format tel que saisi par l'utilisateur).
     *
     * @return array{0: array<int, array>, 1: array<int, string>}
     */
    private function readSheets(\Illuminate\Http\UploadedFile $file): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $sheetNames  = $spreadsheet->getSheetNames();
        $rows        = [];

        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $sheetRows = [];
            foreach ($worksheet->getRowIterator() as $row) {
                $cellValues = [];
                foreach ($row->getCellIterator() as $cell) {
                    $cellValues[] = $cell->getFormattedValue();
                }
                $sheetRows[] = $cellValues;
            }
            $rows[] = $sheetRows;
        }

        return [$rows, $sheetNames];
    }

    /**
     * Si la valeur ressemble a une date, on la normalise en YYYY-MM-DD.
     * Sinon on la retourne telle quelle.
     */
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


    // -------------------------------------------------------------------------
    // IMPORTS AVEC LOGIQUE MÉTIER
    // -------------------------------------------------------------------------

    private function importEntrer(array $headings, array $dataRows): array
    {
        $service = app(EntrerService::class);
        $created = 0; $skipped = 0; $errors = [];

        foreach ($dataRows as $index => $rowValues) {
            $row = $this->rowToAssoc($headings, $rowValues);
            if ($this->isEmptyRow($row)) { $skipped++; continue; }

            $refErrors = [];
            $row = $this->resolveFriendlyReferences($row, $refErrors);
            if (!empty($refErrors)) {
                $errors[] = ['row' => $index + 2, 'errors' => $refErrors];
                continue;
            }

            if (empty($row['article_id']) || empty($row['lieu_stockage_id']) || empty($row['quantite'])) {
                $errors[] = ['row' => $index + 2, 'errors' => ['Champs obligatoires manquants: article_id, lieu_stockage_id, quantite.']];
                continue;
            }

            try {
                $service->createEntrer($row);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index + 2, 'errors' => [$e->getMessage()]];
            }
        }

        return ['created' => $created, 'updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function importSortie(array $headings, array $dataRows): array
    {
        $service = app(SortieService::class);
        $created = 0; $skipped = 0; $errors = [];

        foreach ($dataRows as $index => $rowValues) {
            $row = $this->rowToAssoc($headings, $rowValues);
            if ($this->isEmptyRow($row)) { $skipped++; continue; }

            $refErrors = [];
            $row = $this->resolveFriendlyReferences($row, $refErrors);
            if (!empty($refErrors)) {
                $errors[] = ['row' => $index + 2, 'errors' => $refErrors];
                continue;
            }

            if (empty($row['article_id']) || empty($row['lieu_stockage_id']) || empty($row['quantite'])) {
                $errors[] = ['row' => $index + 2, 'errors' => ['Champs obligatoires manquants: article_id, lieu_stockage_id, quantite.']];
                continue;
            }

            try {
                $service->createSortie($row);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index + 2, 'errors' => [$e->getMessage()]];
            }
        }

        return ['created' => $created, 'updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function importBonCommande(array $headings, array $dataRows): array
    {
        $service = app(BonCommandeService::class);
        $created = 0; $skipped = 0; $errors = [];

        // Regrouper par bon_commande_id temporaire (colonne "id" dans le Excel)
        $bonsByTempId = [];
        $produitsByTempId = [];

        foreach ($dataRows as $index => $rowValues) {
            $row = $this->rowToAssoc($headings, $rowValues);
            if ($this->isEmptyRow($row)) { $skipped++; continue; }

            $tempId = (int) ($row['id'] ?? 0);
            if ($tempId <= 0) { $skipped++; continue; }

            // Si la ligne a des champs de BC principal
            if (!empty($row['date_BC'])) {
                $bonsByTempId[$tempId] = $row;
            }

            // Toujours ajouter comme ligne produit
            $produitsByTempId[$tempId][] = $row;
        }

        // Si pas de groupement (fichier mono-ligne par BC + produit)
        // On tente une approche simple : chaque ligne = 1 BC avec 1 produit
        if (empty($bonsByTempId)) {
            foreach ($dataRows as $index => $rowValues) {
                $row = $this->rowToAssoc($headings, $rowValues);
                if ($this->isEmptyRow($row)) { $skipped++; continue; }

                $refErrors = [];
                $row = $this->resolveFriendlyReferences($row, $refErrors);
                if (!empty($refErrors)) { $errors[] = ['row' => $index + 2, 'errors' => $refErrors]; continue; }

                $required = ['date_BC', 'client_id', 'date_elaboration', 'destination_id', 'date_prevu_livraison'];
                $missing = array_filter($required, fn($k) => empty($row[$k]));
                if (!empty($missing)) {
                    $errors[] = ['row' => $index + 2, 'errors' => ['Champs obligatoires manquants: ' . implode(', ', $missing)]];
                    continue;
                }

                $row['produits'] = [[
                    'article_id'       => $row['article_id'] ?? null,
                    'lieu_stockage_id' => $row['lieu_stockage_id'] ?? null,
                    'unite_id'         => $row['unite_id'] ?? null,
                    'quantite'         => $row['quantite'] ?? 0,
                    'pu'               => $row['pu'] ?? 0,
                    'montant'          => $row['montant'] ?? ($row['quantite'] ?? 0) * ($row['pu'] ?? 0),
                ]];

                try {
                    $service->createBonCommande($row);
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = ['row' => $index + 2, 'errors' => [$e->getMessage()]];
                }
            }
            return ['created' => $created, 'updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
        }

        // Mode groupé
        foreach ($bonsByTempId as $tempId => $bonRow) {
            $refErrors = [];
            $bonRow = $this->resolveFriendlyReferences($bonRow, $refErrors);
            if (!empty($refErrors)) { $errors[] = ['row' => $tempId, 'errors' => $refErrors]; continue; }

            $produits = [];
            foreach ($produitsByTempId[$tempId] ?? [] as $prodRow) {
                $pRefErrors = [];
                $prodRow = $this->resolveFriendlyReferences($prodRow, $pRefErrors);
                if (!empty($pRefErrors)) { $errors[] = ['row' => $tempId, 'errors' => $pRefErrors]; continue 2; }
                $produits[] = [
                    'article_id'       => $prodRow['article_id'] ?? null,
                    'lieu_stockage_id' => $prodRow['lieu_stockage_id'] ?? null,
                    'unite_id'         => $prodRow['unite_id'] ?? null,
                    'quantite'         => $prodRow['quantite'] ?? 0,
                    'pu'               => $prodRow['pu'] ?? 0,
                    'montant'          => $prodRow['montant'] ?? ($prodRow['quantite'] ?? 0) * ($prodRow['pu'] ?? 0),
                ];
            }

            $bonRow['produits'] = $produits;

            try {
                $service->createBonCommande($bonRow);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $tempId, 'errors' => [$e->getMessage()]];
            }
        }

        return ['created' => $created, 'updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function importGasoil(array $headings, array $dataRows): array
    {
        $service = app(GasoilService::class);
        $created = 0; $skipped = 0; $errors = [];

        foreach ($dataRows as $index => $rowValues) {
            $row = $this->rowToAssoc($headings, $rowValues);
            if ($this->isEmptyRow($row)) { $skipped++; continue; }

            $refErrors = [];
            $row = $this->resolveFriendlyReferences($row, $refErrors);
            if (!empty($refErrors)) {
                $errors[] = ['row' => $index + 2, 'errors' => $refErrors];
                continue;
            }

            $required = ['num_bon', 'materiel_id', 'quantite'];
            $missing = array_filter($required, fn($k) => empty($row[$k]));
            if (!empty($missing)) {
                $errors[] = ['row' => $index + 2, 'errors' => ['Champs obligatoires manquants: ' . implode(', ', $missing)]];
                continue;
            }

            try {
                $service->createVersement([
                    'num_bon'                => $row['num_bon'],
                    'materiel_id_cible'      => $row['materiel_id'],
                    'quantite'               => $row['quantite'],
                    'source_lieu_stockage_id' => $row['lieu_stockage_id'] ?? null,
                    'prix_gasoil'            => $row['prix_gasoil'] ?? null,
                    'date_operation'         => $row['date_operation'] ?? $row['date'] ?? null,
                    'ajouter_par'            => 'import',
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index + 2, 'errors' => [$e->getMessage()]];
            }
        }

        return ['created' => $created, 'updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function importHuile(array $headings, array $dataRows): array
    {
        $service = app(HuileService::class);
        $created = 0; $skipped = 0; $errors = [];

        foreach ($dataRows as $index => $rowValues) {
            $row = $this->rowToAssoc($headings, $rowValues);
            if ($this->isEmptyRow($row)) { $skipped++; continue; }

            $refErrors = [];
            $row = $this->resolveFriendlyReferences($row, $refErrors);
            if (!empty($refErrors)) {
                $errors[] = ['row' => $index + 2, 'errors' => $refErrors];
                continue;
            }

            $required = ['num_bon', 'materiel_id', 'article_id', 'quantite', 'lieu_stockage_id'];
            $missing = array_filter($required, fn($k) => empty($row[$k]));
            if (!empty($missing)) {
                $errors[] = ['row' => $index + 2, 'errors' => ['Champs obligatoires manquants: ' . implode(', ', $missing)]];
                continue;
            }

            try {
                $service->createVersement([
                    'num_bon'                => $row['num_bon'],
                    'materiel_id_cible'      => $row['materiel_id'],
                    'subdivision_id_cible'   => $row['subdivision_id'] ?? null,
                    'article_versement_id'   => $row['article_id'],
                    'source_lieu_stockage_id' => $row['lieu_stockage_id'],
                    'quantite'               => $row['quantite'],
                    'date_operation'         => $row['date_operation'] ?? $row['date'] ?? null,
                    'ajouter_par'            => 'import',
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index + 2, 'errors' => [$e->getMessage()]];
            }
        }

        return ['created' => $created, 'updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function importBonLivraison(array $headings, array $dataRows): array
    {
        $service = app(BonLivraisonService::class);
        $created = 0; $skipped = 0; $errors = [];

        foreach ($dataRows as $index => $rowValues) {
            $row = $this->rowToAssoc($headings, $rowValues);
            if ($this->isEmptyRow($row)) { $skipped++; continue; }

            $refErrors = [];
            $row = $this->resolveFriendlyReferences($row, $refErrors);
            if (!empty($refErrors)) {
                $errors[] = ['row' => $index + 2, 'errors' => $refErrors];
                continue;
            }

            $required = ['numBL', 'date_livraison', 'heure_depart', 'vehicule_id', 'chauffeur_id', 'bon_commande_produit_id', 'quantite', 'PU', 'gasoil_depart', 'nbr_voyage'];
            $missing = array_filter($required, fn($k) => empty($row[$k]));
            if (!empty($missing)) {
                $errors[] = ['row' => $index + 2, 'errors' => ['Champs obligatoires manquants: ' . implode(', ', $missing)]];
                continue;
            }

            try {
                $service->createBonLivraison(array_merge($row, ['user_name' => 'import']));
                $created++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index + 2, 'errors' => [$e->getMessage()]];
            }
        }

        return ['created' => $created, 'updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function menuTemplates(): array
    {
        return [
            ['key' => 'materiel', 'label' => 'Matériel', 'source' => 'materiels', 'required_fields' => ['nom_materiel', 'status', 'categorie']],
            ['key' => 'atelier_materiel', 'label' => 'Atelier matériel', 'source' => 'operation_atelier_mecas', 'required_fields' => ['materiel_id', 'gasoil_retirer', 'quantite_retiree_cm', 'reste_gasoil']],
            ['key' => 'gasoil', 'label' => 'Gasoil', 'source' => 'gasoils', 'required_fields' => ['type_operation', 'quantite']],
            ['key' => 'huile', 'label' => 'Huile', 'source' => 'huiles', 'required_fields' => ['type_operation', 'quantite']],
            ['key' => 'pneu', 'label' => 'Pneu', 'source' => 'pneus', 'required_fields' => ['reference_pneu', 'marque', 'dimension']],
            ['key' => 'fourniture', 'label' => 'Fourniture', 'source' => 'fournitures', 'required_fields' => ['nom_fourniture']],
            ['key' => 'production', 'label' => 'Production', 'source' => 'production_produits', 'required_fields' => ['production_id', 'produit_id', 'quantite', 'unite_id']],
            ['key' => 'bon_commande', 'label' => 'Bon de commande', 'source' => 'bon_commandes', 'required_fields' => ['numero', 'date_BC', 'client_id|client_nom', 'destination_id|destination_nom', 'date_prevu_livraison']],
            ['key' => 'bon_livraison', 'label' => 'Bon de livraison', 'source' => 'bon_livraisons', 'required_fields' => ['numBL', 'bon_commande_produit_id']],
            ['key' => 'melange', 'label' => 'Mélange', 'source' => 'melange_produits', 'required_fields' => ['date', 'produit_a_id', 'produit_b_id', 'produit_b_final_id']],
            ['key' => 'bon_transfert', 'label' => 'Bon de transfert', 'source' => 'bon_transferts', 'required_fields' => ['numero_bon', 'date', 'produit_id', 'quantite']],
            ['key' => 'transfert', 'label' => 'Transfert', 'source' => 'transfert_produits', 'required_fields' => ['date', 'materiel_id', 'produit_id', 'quantite']],
            ['key' => 'vente', 'label' => 'Vente', 'source' => 'ventes', 'required_fields' => ['date', 'client_id', 'materiel_id', 'bl_id', 'produit_id', 'quantite']],
            ['key' => 'entrer', 'label' => 'Entrée', 'source' => 'entrers', 'required_fields' => ['user_name', 'article_id', 'categorie_article_id', 'lieu_stockage_id', 'unite_id', 'quantite']],
            ['key' => 'sortie', 'label' => 'Sortie', 'source' => 'sorties', 'required_fields' => ['user_name', 'article_id', 'categorie_article_id', 'lieu_stockage_id', 'unite_id', 'quantite']],
            ['key' => 'stock', 'label' => 'Stock', 'source' => 'stocks', 'required_fields' => ['article_id', 'categorie_article_id', 'lieu_stockage_id', 'quantite']],
        ];
    }
}
