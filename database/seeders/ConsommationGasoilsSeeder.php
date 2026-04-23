<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConsommationGasoilsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer tous les production_materiels
        $productionMateriels = DB::table('production_materiels')->get();
        $consommations = [];

        foreach ($productionMateriels as $pm) {
            // Récupérer les infos de la production associée
            $production = DB::table('produits')->where('id', $pm->production_id)->first();

            // Créer des variations de consommation pour simuler des pics
            $quantiteBase = $pm->consommation_totale;
            $variation = $this->getVariationAleatoire($pm->date_consommation ?? $production->date_prod);
            $quantiteFinale = $quantiteBase * (1 + $variation / 100);

            $consommations[] = [
                'vehicule_id' => 8, // Pelle HITACHI
                'consommation_reelle_par_heure' => $pm->consommation_reelle_par_heure * (1 + $variation / 100),
                'consommation_horaire_reference' => $pm->consommation_horaire_reference,
                'ecart_consommation_horaire' => $pm->ecart_consommation_horaire,
                'statut_consommation_horaire' => $this->getStatutAvecVariation($pm->statut_consommation_horaire, $variation),
                'consommation_totale' => round($quantiteFinale, 2),
                'consommation_destination_reference' => $pm->consommation_destination_reference,
                'ecart_consommation_destination' => $pm->ecart_consommation_destination,
                'statut_consommation_destination' => $pm->statut_consommation_destination,
                'bon_livraison_id' => null,
                'transfert_produit_id' => null ,
                'production_materiel_id' => $pm->id,
                'destination_id' => null,
                'quantite' => round($quantiteFinale, 2),
                'distance_km' => rand(0, 100) > 20 ? rand(10, 100) : null, // 80% chance d'avoir une distance
                'date_consommation' => $production->date_prod,
                'created_at' => $production->created_at,
                'updated_at' => $production->updated_at,
            ];
        }

        DB::table('consommation_gasoils')->insert($consommations);
    }

    /**
     * Génère une variation aléatoire pour simuler des pics de consommation
     * Basé sur la date pour créer des patterns
     */
    private function getVariationAleatoire(string $date): float
    {
        $dateObj = Carbon::parse($date);
        $jour = $dateObj->day;
        $mois = $dateObj->month;

        // Créer des patterns de consommation
        // - Pics les lundis et vendredis
        // - Consommation plus basse les weekends
        // - Variations saisonnières

        $variationBase = 0;

        // Variation par jour de la semaine
        $jourSemaine = $dateObj->dayOfWeek;
        if ($jourSemaine == 1) { // Lundi
            $variationBase += rand(10, 20); // Pic de démarrage
        } elseif ($jourSemaine == 5) { // Vendredi
            $variationBase += rand(5, 15); // Pic de fin de semaine
        } elseif ($jourSemaine == 0 || $jourSemaine == 6) { // Weekend
            $variationBase -= rand(20, 40); // Consommation réduite
        }

        // Variation aléatoire additionnelle
        $variationAleatoire = rand(-15, 15);

        // Créer quelques pics extrêmes (1 fois par mois environ)
        if ($jour == 15 || $jour == 25) {
            $variationBase += rand(30, 60); // Pic important
        }

        return $variationBase + $variationAleatoire;
    }

    private function getStatutAvecVariation(string $statutBase, float $variation): string
    {
        if ($variation > 30) {
            return 'tres_elevee';
        } elseif ($variation > 15) {
            return 'elevee';
        } elseif ($variation < -20) {
            return 'tres_basse';
        } elseif ($variation < -10) {
            return 'basse';
        }

        return $statutBase;
    }
}
