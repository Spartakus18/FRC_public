<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterielSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('materiels')->insert([
            'nom_materiel' => '4*4 Pick Up FORD',
            'nbr_pneu' => 4,
            'categorie' => 'vehicule',
            'seuil' => 2,
            'consommation_horaire' => 6,
            'capaciteL' => 20,
            'capaciteCm' => 2.5,
            'actuelGasoil' => 5,
            'compteur_actuel' => 0
        ]);
        DB::table('materiels')->insert([
            'nom_materiel' => 'ATEGO',
            'nbr_pneu' => 16,
            'categorie' => 'vehicule',
            'seuil' => 1,
            'consommation_horaire' => 10,
            'capaciteL' => 20,
            'capaciteCm' => 3,
            'actuelGasoil' => 7,
            'compteur_actuel' => 0
        ]);
        DB::table('materiels')->insert([
            'nom_materiel' => 'BELL T17',
            'categorie' => 'engin',
            'consommation_horaire' => 5,
            'seuil' => 3,
            'capaciteL' => 20,
            'capaciteCm' => 5,
            'actuelGasoil' => 7,
            'compteur_actuel' => 0
        ]);
        DB::table('materiels')->insert([
            'nom_materiel' => 'HOWO 1 5630TCB',
            'nbr_pneu' => 4,
            'categorie' => 'vehicule',
            'seuil' => 2,
            'consommation_horaire' => 6.4,
            'capaciteL' => 20,
            'capaciteCm' => 4,
            'actuelGasoil' => 10,
            'compteur_actuel' => 0
        ]);
        DB::table('materiels')->insert([
            'nom_materiel' => 'MAN 1 TGA 2095AJ',
            'nbr_pneu' => 6,
            'categorie' => 'vehicule',
            'seuil' => 3,
            'capaciteL' => 20,
            'capaciteCm' => 3,
            'consommation_horaire' => 5.4,
            'actuelGasoil' => 10,
            'compteur_actuel' => 0
        ]);
        // DB::table('materiels')->insert([
        //     'nom_materiel' => 'MAN 2 TGA 0126TBV',
        //     'nbr_pneu' => 4,
        //     'categorie' => 'vehicule',
        //     'seuil' => 3,
        //     'capaciteL' => 20,
        //     'capaciteCm' => 3,
        //     'consommation_horaire' => 5.4,
        //     'actuelGasoil' => 10,
        //     'compteur_actuel' => 0
        // ]);
        // DB::table('materiels')->insert([
        //     'nom_materiel' => 'Pelle CAT 325-D',
        //     'categorie' => 'engin',
        //     'consommation_horaire' => 18,
        //     'seuil' => 1,
        //     'capaciteL' => 20,
        //     'capaciteCm' => 3.4,
        //     'consommation_horaire' => 5.4,
        //     'actuelGasoil' => 10,
        //     'compteur_actuel' => 0
        // ]);
        // DB::table('materiels')->insert([
        //     'nom_materiel' => 'Pelle HITACHI',
        //     'categorie' => 'engin',
        //     'seuil' => 2,
        //     'capaciteL' => 20,
        //     'capaciteCm' => 3,
        //     'consommation_horaire' => 5.4,
        //     'actuelGasoil' => 10,
        //     'compteur_actuel' => 0
        // ]);
        // DB::table('materiels')->insert([
        //     'nom_materiel' => 'TRAX 936',
        //     'categorie' => 'engin',
        //     'consommation_horaire' => 12,
        //     'seuil' => 3,
        //     'capaciteL' => 20,
        //     'capaciteCm' => 3,
        //     'consommation_horaire' => 5.4,
        //     'actuelGasoil' => 10,
        //     'compteur_actuel' => 0
        // ]);
        // DB::table('materiels')->insert([
        //     'nom_materiel' => 'TRAX 950 E',
        //     'categorie' => 'engin',
        //     'consommation_horaire' => 12,
        //     'seuil' => 3,
        //     'capaciteL' => 20,
        //     'capaciteCm' => 3,
        //     'consommation_horaire' => 5.4,
        //     'actuelGasoil' => 10,
        //     'compteur_actuel' => 0
        // ]);
        // DB::table('materiels')->insert([
        //     'nom_materiel' => 'GE 2 VERT',
        //     'categorie' => 'groupe',
        //     'consommation_horaire' => 24,
        //     'seuil' => 3,
        //     'capaciteL' => 20,
        //     'capaciteCm' => 3,
        //     'consommation_horaire' => 5.4,
        //     'actuelGasoil' => 10,
        //     'compteur_actuel' => 0
        // ]);
        // DB::table('materiels')->insert([
        //     'nom_materiel' => 'GE OLYMPIAN JAUNE',
        //     'categorie' => 'groupe',
        //     'seuil' => 3,
        //     'capaciteL' => 20,
        //     'capaciteCm' => 3,
        //     'consommation_horaire' => 5.4,
        //     'actuelGasoil' => 10,
        //     'compteur_actuel' => 0
        // ]);
        // DB::table('materiels')->insert([
        //     'nom_materiel' => 'Groupe KUBOTA',
        //     'categorie' => 'groupe',
        //     'seuil' => 3,
        //     'capaciteL' => 20,
        //     'capaciteCm' => 3,
        //     'consommation_horaire' => 5.4,
        //     'actuelGasoil' => 10,
        //     'compteur_actuel' => 0
        // ]);
        // DB::table('materiels')->insert([
        //     'nom_materiel' => 'Groupe TOTAL',
        //     'categorie' => 'groupe',
        //     'seuil' => 3,
        //     'capaciteL' => 20,
        //     'capaciteCm' => 3,
        //     'consommation_horaire' => 5.4,
        //     'actuelGasoil' => 10,
        //     'compteur_actuel' => 0
        // ]);
    }
}
