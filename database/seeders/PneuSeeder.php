<?php

namespace Database\Seeders;
use App\Models\Parametre\Materiel;
use App\Models\Parametre\Pneu;
use Illuminate\Database\Seeder;

class PneuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $pneusData = [
            // Pneus avec véhicules (10 premiers)
            [
                'date_obtention' => '2024-12-02',
                'date_mise_en_service' => '2025-08-06',
                'etat' => 'bonne',
                'caracteristiques' => '12R/22,5',
                'marque' => 'KINGRUN',
                'num_serie' => '4H29331207',
                'type' => 'GA 267',
                'situation' => 'en_service',
                'materiel_id' => Materiel::where('nom_materiel', 'LIKE', '%MAN 1%')->first()->id,
                'emplacement' => 'MGEXT',
                'kilometrage' => 0,
                'observations' => ''
            ],
            [
                'date_obtention' => '2025-05-01',
                'date_mise_en_service' => '2025-05-01',
                'etat' => 'bonne',
                'caracteristiques' => '13R/22,5',
                'marque' => 'SPORTRAK',
                'num_serie' => '4120120077',
                'type' => 'SP 935',
                'situation' => 'en_service',
                'materiel_id' => Materiel::where('nom_materiel', 'LIKE', '%MAN 1%')->first()->id,
                'emplacement' => 'DEV G',
                'kilometrage' => 0,
                'observations' => ''
            ],
            [
                'date_obtention' => '2025-07-26',
                'date_mise_en_service' => '2025-07-26',
                'etat' => 'bonne',
                'caracteristiques' => '13R/22,5',
                'marque' => 'DOUPRO',
                'num_serie' => 'G211C36109',
                'type' => 'ST 869',
                'situation' => 'en_service',
                'materiel_id' => Materiel::where('nom_materiel', 'LIKE', '%MAN 1%')->first()->id,
                'emplacement' => 'AR G EXT',
                'kilometrage' => 0,
                'observations' => ''
            ],
            [
                'date_obtention' => '2025-05-05',
                'date_mise_en_service' => '2025-07-26',
                'etat' => 'bonne',
                'caracteristiques' => '13R/22,5',
                'marque' => 'SPORTRAK',
                'num_serie' => '4120184107',
                'type' => 'SP 935',
                'situation' => 'en_service',
                'materiel_id' => Materiel::where('nom_materiel', 'LIKE', '%MAN 1%')->first()->id,
                'emplacement' => 'AR G INT',
                'kilometrage' => 0,
                'observations' => ''
            ],
            [
                'date_obtention' => '2025-05-01',
                'date_mise_en_service' => '2025-08-06',
                'etat' => 'bonne',
                'caracteristiques' => '12R/22,5',
                'marque' => 'SPORTRAK',
                'num_serie' => '1518102',
                'type' => 'SP 909',
                'situation' => 'en_service',
                'materiel_id' => Materiel::where('nom_materiel', 'LIKE', '%MAN 1%')->first()->id,
                'emplacement' => 'M D EXT',
                'kilometrage' => 0,
                'observations' => ''
            ],
            [
                'date_obtention' => '2024-12-02',
                'date_mise_en_service' => '2025-08-11',
                'etat' => 'bonne',
                'caracteristiques' => '12R/22,5',
                'marque' => 'KINGRUN',
                'num_serie' => 'A303007900',
                'type' => 'GA 267',
                'situation' => 'en_service',
                'materiel_id' => Materiel::where('nom_materiel', 'LIKE', '%MAN 1%')->first()->id,
                'emplacement' => 'M G INT',
                'kilometrage' => 0,
                'observations' => ''
            ],

            // Pneus sans véhicules (2 derniers)
            [
                'date_obtention' => '2025-01-16',
                'date_mise_en_service' => null,
                'etat' => 'bonne',
                'caracteristiques' => '13R/22,5',
                'marque' => 'DOUPRO',
                'num_serie' => 'S152A35079',
                'type' => 'ST 869',
                'situation' => 'en_service',
                'materiel_id' => null,
                'emplacement' => null,
                'kilometrage' => 0,
                'observations' => '2025-08-08 00:00:00'
            ],
            [
                'date_obtention' => '2025-12-02',
                'date_mise_en_service' => null,
                'etat' => 'bonne',
                'caracteristiques' => '13R/22,5',
                'marque' => 'ROADSHINE',
                'num_serie' => '1310211116',
                'type' => 'RS 617',
                'situation' => 'en_service',
                'materiel_id' => null,
                'emplacement' => null,
                'kilometrage' => 0,
                'observations' => '2025-08-08 00:00:00'
            ]
        ];

        foreach ($pneusData as $pneuData) {
            Pneu::create($pneuData);
        }
    }
}
