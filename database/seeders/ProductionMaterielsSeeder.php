<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductionMaterielsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Récupérer tous les produits créés
        $produits = DB::table('produits')->where('id', '>=', 1)->get();
        $productionMateriels = [];

        foreach ($produits as $index => $produit) {
            $compteurDebut = rand(1000, 2000);
            $compteurFin = $compteurDebut + rand(20, 200);
            $gasoilDebut = rand(100, 300);
            $consommationTotale = rand(50, 250);
            $gasoilFin = max(0, $gasoilDebut - $consommationTotale);

            // Calcul de la durée en heures
            $heureDebut = Carbon::parse($produit->heure_debut);
            $heureFin = Carbon::parse($produit->heure_fin);
            $dureeHeures = $heureDebut->diffInHours($heureFin);

            $consommationParHeure = $dureeHeures > 0 ? $consommationTotale / $dureeHeures : 0;

            $productionMateriels[] = [
                'production_id' => $produit->id,
                'materiel_id' => 8, // Pelle HITACHI
                'categorie_travail_id' => 1,
                'heure_debut' => $produit->heure_debut,
                'heure_fin' => $produit->heure_fin,
                'compteur_debut' => $compteurDebut,
                'compteur_fin' => $compteurFin,
                'gasoil_debut' => $gasoilDebut,
                'gasoil_fin' => $gasoilFin,
                'consommation_reelle_par_heure' => round($consommationParHeure, 2),
                'consommation_horaire_reference' => rand(20, 40),
                'ecart_consommation_horaire' => round(rand(-10, 10), 2),
                'statut_consommation_horaire' => $this->getStatutConsommation(),
                'consommation_totale' => $consommationTotale,
                'consommation_destination_reference' => rand(100, 300),
                'ecart_consommation_destination' => round(rand(-50, 50), 2),
                'statut_consommation_destination' => $this->getStatutConsommation(),
                'observation' => $this->getObservationAleatoire(),
                'created_at' => $produit->created_at,
                'updated_at' => $produit->updated_at,
            ];
        }

        DB::table('production_materiels')->insert($productionMateriels);
    }

    private function getStatutConsommation(): string
    {
        $statuts = ['normal', 'elevee', 'basse', 'anormale'];
        $probas = [70, 15, 10, 5]; // 70% normal, 15% élevée, etc.

        $rand = rand(1, 100);
        $cumul = 0;

        foreach ($statuts as $index => $statut) {
            $cumul += $probas[$index];
            if ($rand <= $cumul) {
                return $statut;
            }
        }

        return 'normal';
    }

    private function getObservationAleatoire(): string
    {
        $observations = [
            'Production normale sans incident',
            'Matériel en bon état',
            'Besoin de maintenance légère',
            'Problème de démarrage',
            'Usure normale des pièces',
            'Température moteur élevée',
            'Consommation dans la moyenne',
            'Performance optimale',
            'Petit problème technique résolu',
            'Nécessite révision prochaine',
        ];

        return $observations[array_rand($observations)];
    }
}
