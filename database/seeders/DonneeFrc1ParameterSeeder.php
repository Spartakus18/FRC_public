<?php

namespace Database\Seeders;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\Huile\Subdivision;
use App\Models\Location\AideChauffeur;
use App\Models\Location\Conducteur;
use App\Models\Parametre\Client;
use App\Models\Parametre\Destination;
use App\Models\Parametre\Materiel;
use App\Models\Parametre\Unite;
use App\Models\Produit\Categorie;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class DonneeFrc1ParameterSeeder extends Seeder
{
    public function run(): void
    {
        $workbookPath = $this->resolveWorkbookPath();
        $spreadsheet  = IOFactory::load($workbookPath);

        $this->seedReglage($this->requireSheet($spreadsheet, 'Reglage'));
        $this->seedArticleDepot($this->requireSheet($spreadsheet, 'article-depot'));
        $this->seedMateriels($this->requireSheet($spreadsheet, 'Materiel'));

        $this->command?->info("DonneeFrc1ParameterSeeder termine depuis: {$workbookPath}");
    }

    private function seedReglage(Worksheet $sheet): void
    {
        $rows    = $sheet->toArray(null, true, true, false);
        $headers = array_map([$this, 'normalizeHeader'], $rows[0] ?? []);

        foreach (array_slice($rows, 1) as $row) {
            foreach ($headers as $index => $header) {
                $value = $this->cleanCell($row[$index] ?? null);
                if ($value === null) {
                    continue;
                }

                match ($header) {
                    'nom_client' => Client::firstOrCreate(['nom_client' => $value]),
                    'chauffeur' => Conducteur::firstOrCreate(['nom_conducteur' => $value]),
                    'aide_chauffeur' => AideChauffeur::firstOrCreate(['nom_aideChauffeur' => $value]),
                    'lieu_stockage' => Lieu_stockage::firstOrCreate(['nom' => $value]),
                    'subdivision_materiel' => Subdivision::firstOrCreate(['nom_subdivision' => $value]),
                    'categorie_travail' => Categorie::firstOrCreate(['nom_categorie' => $value]),
                    'unite' => Unite::firstOrCreate(['nom_unite' => $value]),
                    'destination' => Destination::firstOrCreate(
                        ['nom_destination' => $value],
                        ['consommation_reference' => null]
                    ),
                    default => null,
                };
            }
        }
    }

    private function seedArticleDepot(Worksheet $sheet): void
    {
        $rows = $sheet->toArray(null, true, true, false);

        foreach (array_slice($rows, 1) as $row) {
            $nomArticle = $this->cleanCell($row[0] ?? null);
            if ($nomArticle === null) {
                continue;
            }

            ArticleDepot::firstOrCreate(['nom_article' => $nomArticle]);
        }
    }

    private function seedMateriels(Worksheet $sheet): void
    {
        $rows     = $sheet->toArray(null, true, true, false);
        $headers  = array_map([$this, 'normalizeHeader'], $rows[0] ?? []);
        $fieldMap = [
            'nom_materiel' => 'nom_materiel',
            'categorie' => 'categorie',
            'consommation_horraire' => 'consommation_horaire',
            'consommation_horaire' => 'consommation_horaire',
            'ref_gasoil_20l' => 'capaciteCm',
            'seuil_securite' => 'seuil',
        ];

        foreach (array_slice($rows, 1) as $row) {
            $payload = [];

            foreach ($headers as $index => $header) {
                if (!isset($fieldMap[$header])) {
                    continue;
                }

                $value = $this->cleanCell($row[$index] ?? null);
                if ($value === null) {
                    continue;
                }

                $payload[$fieldMap[$header]] = $value;
            }

            if (empty($payload['nom_materiel'])) {
                continue;
            }

            if (isset($payload['categorie'])) {
                $payload['categorie'] = strtolower($payload['categorie']);
            }

            foreach (['consommation_horaire', 'capaciteCm', 'seuil'] as $numericField) {
                if (isset($payload[$numericField])) {
                    $payload[$numericField] = $this->toFloat($payload[$numericField]);
                }
            }

            // Le fichier ne fournit qu'une reference "20L"; on initialise donc la capacite litre sur cette base.
            if (isset($payload['capaciteCm']) && !isset($payload['capaciteL'])) {
                $payload['capaciteL'] = 20;
            }

            $payload += [
                'status' => true,
                'actuelGasoil' => 0,
                'compteur_actuel' => 0,
                'gasoil_consommation' => 0,
                'seuil_notif' => false,
            ];

            Materiel::updateOrCreate(
                ['nom_materiel' => $payload['nom_materiel']],
                $payload
            );
        }
    }

    private function requireSheet(Spreadsheet $spreadsheet, string $sheetName): Worksheet
    {
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet instanceof Worksheet) {
            throw new RuntimeException("Feuille introuvable dans donnée frc-1.xlsx: {$sheetName}");
        }

        return $sheet;
    }

    private function resolveWorkbookPath(): string
    {
        $configuredPath = env('FRC_DONNEE_FRC_1_PATH');
        if (is_string($configuredPath) && is_file($configuredPath)) {
            return $configuredPath;
        }

        $candidates = [
            base_path('database/seeders/data/donnee-frc-1.xlsx'),
            base_path('database/seeders/data/donnée frc-1.xlsx'),
            'D:/donnée frc-1.xlsx',
            'D:/donnee frc-1.xlsx',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        foreach (array_merge(glob('D:/*.xlsx') ?: [], glob(base_path('database/seeders/data/*.xlsx')) ?: []) as $candidate) {
            if (str_contains(strtolower($candidate), 'frc-1')) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            "Impossible de trouver donnée frc-1.xlsx. Placez le fichier sur D: ou renseignez FRC_DONNEE_FRC_1_PATH."
        );
    }

    private function normalizeHeader(mixed $value): string
    {
        $header = trim((string) $value);
        $header = str_replace([' ', '-'], '_', $header);
        $header = preg_replace('/_+/', '_', $header) ?? $header;

        return strtolower(trim($header, '_'));
    }

    private function cleanCell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return $value;
    }

    private function toFloat(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}