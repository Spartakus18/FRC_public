<?php

namespace Database\Seeders;

use App\Models\Fourniture\Fourniture;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class FournitureSeeder extends Seeder
{
    public function run()
    {
        $fournitures = [
            [
                'nom_article' => 'Pneu MICHELIN 225/45 R17',
                'reference' => 'PT-225-45-17-MIC',
                'numero_serie' => 'PT001',
                'etat' => 'neuf',
                'is_dispo' => true,
                'date_acquisition' => '2024-01-15',
                'localisation_actuelle' => 'stock',
                'commentaire' => 'Pneu neuf, jamais utilisé',
            ],
            [
                'nom_article' => 'Disque de frein avant',
                'reference' => 'DF-AV-BMW-320',
                'numero_serie' => 'DF001',
                'etat' => 'bon',
                'is_dispo' => false,
                'date_acquisition' => '2023-11-20',
                'materiel_id_associe' => 1, // ID d'un matériel existant
                'date_sortie_stock' => '2024-02-10',
                'localisation_actuelle' => 'chantier',
                'commentaire' => 'Disque de frein en cours d\'utilisation',
            ],
            [
                'nom_article' => 'Batterie 12V 70Ah',
                'reference' => 'BAT-12V-70AH',
                'numero_serie' => 'BAT001',
                'etat' => 'moyen',
                'is_dispo' => true,
                'date_acquisition' => '2023-08-05',
                'localisation_actuelle' => 'stock',
                'commentaire' => 'Batterie reconditionnée, capacité 70%',
            ],
            [
                'nom_article' => 'Kit plaquettes de frein',
                'reference' => 'KPF-AUDI-A4',
                'numero_serie' => 'KPF001',
                'etat' => 'a_verifier',
                'is_dispo' => true,
                'date_acquisition' => '2024-01-30',
                'localisation_actuelle' => 'atelier',
                'commentaire' => 'À vérifier avant utilisation',
            ],
            [
                'nom_article' => 'Alternateur 90A',
                'reference' => 'ALT-90A-PEUGEOT',
                'numero_serie' => 'ALT001',
                'etat' => 'hors_service',
                'is_dispo' => false,
                'date_acquisition' => '2022-06-15',
                'localisation_actuelle' => 'atelier_maintenance',
                'commentaire' => 'Alternateur hors service, besoin de réparation',
            ],
        ];

        foreach ($fournitures as $fourniture) {
            Fourniture::create($fourniture);
        }
    }
}
