<?php

namespace Database\Seeders;

use App\Models\Parametre\Materiel;
use App\Models\Parametre\Pneu;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HistoriquePneuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = \Faker\Factory::create('fr_FR');
        $typesAction = ['ajout', 'transfert', 'retrait', 'mise_hors_service', 'reparation'];
        $etats = ['bonne', 'usée', 'endommagée', 'défectueuse'];

        $historiques = [];
        $pneus = Pneu::all();
        $materiels = Materiel::all();

        foreach ($pneus as $pneu) {
            $nbEvenements = $faker->numberBetween(1, 4); // 1 à 4 événements par pneu

            // Vérifier que la date d'obtention n'est pas dans le futur
            $dateCourante = $pneu->date_obtention;
            if (Carbon::parse($dateCourante)->isFuture()) {
                // Si la date est dans le futur, utiliser une date aléatoire dans le passé
                $dateCourante = $faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d');
            }

            $materielCourant = $pneu->materiel_id;

            for ($i = 0; $i < $nbEvenements; $i++) {
                // Déterminer le type d'action
                if ($i === 0) {
                    $typeAction = 'ajout'; // Premier événement toujours un ajout
                } else {
                    $typeAction = $faker->randomElement(['transfert', 'reparation', 'mise_hors_service']);
                }

                // Préparer les données selon le type d'action
                $ancienMaterielId = $materielCourant;
                $nouveauMaterielId = $materielCourant;
                $ancienMaterielNom = $ancienMaterielId ? $materiels->find($ancienMaterielId)->nom_materiel : null;
                $nouveauMaterielNom = $nouveauMaterielId ? $materiels->find($nouveauMaterielId)->nom_materiel : null;

                switch ($typeAction) {
                    case 'ajout':
                        $nouveauMaterielId = $pneu->materiel_id;
                        $nouveauMaterielNom = $nouveauMaterielId ? $materiels->find($nouveauMaterielId)->nom_materiel : null;
                        $etat = 'bonne';
                        break;

                    case 'transfert':
                        // Changer de matériel
                        $nouveauMaterielId = $faker->randomElement($materiels->pluck('id')->toArray());
                        while ($nouveauMaterielId === $ancienMaterielId) {
                            $nouveauMaterielId = $faker->randomElement($materiels->pluck('id')->toArray());
                        }
                        $nouveauMaterielNom = $materiels->find($nouveauMaterielId)->nom_materiel;
                        $etat = $faker->randomElement($etats);
                        $materielCourant = $nouveauMaterielId; // Mettre à jour le matériel courant
                        break;

                    case 'reparation':
                        $etat = 'bonne'; // Après réparation, l'état redevient "bonne"
                        break;

                    case 'mise_hors_service':
                        $nouveauMaterielId = null;
                        $nouveauMaterielNom = null;
                        $etat = $faker->randomElement(['usée', 'défectueuse']);
                        $materielCourant = null;
                        break;

                    default:
                        $etat = $pneu->etat;
                        break;
                }

                // Avancer la date pour le prochain événement avec vérification
                $dateDebut = Carbon::parse($dateCourante);
                $dateFin = Carbon::now();

                // S'assurer que la date de début est antérieure à la date de fin
                if ($dateDebut->lt($dateFin)) {
                    $dateCourante = $faker->dateTimeBetween($dateCourante, 'now')->format('Y-m-d');
                } else {
                    // Si la date courante est dans le futur, utiliser une date aléatoire dans le passé récent
                    $dateCourante = $faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d');
                }

                $historiques[] = [
                    'pneu_id' => $pneu->id,
                    'ancien_materiel_id' => $ancienMaterielId,
                    'ancien_materiel_nom' => $ancienMaterielNom,
                    'nouveau_materiel_id' => $nouveauMaterielId,
                    'nouveau_materiel_nom' => $nouveauMaterielNom,
                    'etat' => $etat,
                    'type_action' => $typeAction,
                    'date_action' => $dateCourante,
                    'commentaire' => $faker->optional(0.6)->sentence(6),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];

                // Espacer les événements d'au moins quelques jours avec vérification
                $dateCourante = Carbon::parse($dateCourante);
                if ($dateCourante->lt(Carbon::now()->subDays(1))) {
                    $dateCourante = $dateCourante->addDays($faker->numberBetween(1, 30))->format('Y-m-d');
                } else {
                    // Si on est trop proche de la date actuelle, arrêter la boucle
                    break;
                }
            }
        }

        // Insertion par lots pour optimiser les performances
        $chunks = array_chunk($historiques, 100);
        foreach ($chunks as $chunk) {
            DB::table('historique_pneus')->insert($chunk);
        }
    }
}
