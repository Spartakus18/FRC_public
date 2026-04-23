<?php

namespace App\Models;

use App\Models\Parametre\Materiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerteGasoil extends Model
{
    use HasFactory;

    protected $table = 'perte_gasoils';

    protected $fillable = [
        'materiel_id',
        'quantite_precedente',
        'quantite_actuelle',
        'quantite_perdue',
        'raison_perte',

        'quantite_precedente_soir',
        'quantite_actuelle_soir',
        'quantite_perdue_soir',
        'raison_perte_soir',
    ];

    /**
     * Calcule la quantité perdue basée sur les quantités précédente et actuelle
     *
     * @param float $quantitePrecedente
     * @param float $quantiteActuelle
     * @return float
     */
    public static function calculerQuantitePerdue(float $quantitePrecedente, float $quantiteActuelle): float
    {
        // Si la quantité actuelle est supérieure ou égale à la précédente, pas de perte
        // Si la quantité actuelle est inférieure, on calcule la différence
        return max(0, $quantitePrecedente - $quantiteActuelle);
    }

    /**
     * Crée plusieurs enregistrements de perte de gasoil à partir des données du front
     * (Les données sont déjà validées par le contrôleur)
     *
     * @param array $donneesFront
     * @return array
     */
    public static function creerPertesDepuisFront(array $donneesFront): array
    {
        $resultats = [
            'succes' => [],
            'echecs' => []
        ];

        foreach ($donneesFront as $donnee) {
            try {
                // Calcul de la quantité perdue
                $quantitePerdue = self::calculerQuantitePerdue(
                    $donnee['gasoilHier'],
                    $donnee['gasoilMaj']
                );

                // Création de l'enregistrement
                $perteGasoil = self::create([
                    'materiel_id' => $donnee['id'],
                    'quantite_precedente' => $donnee['gasoilHier'],
                    'quantite_actuelle' => $donnee['gasoilMaj'],
                    'quantite_perdue' => $quantitePerdue,
                    'raison_perte' => $donnee['motif'] ?? null,
                ]);

                $resultats['succes'][] = [
                    'id' => $perteGasoil->id,
                    'materiel_id' => $perteGasoil->materiel_id,
                    'quantite_perdue' => $perteGasoil->quantite_perdue,
                ];
            } catch (\Exception $e) {
                $resultats['echecs'][] = [
                    'data' => $donnee,
                    'erreurs' => [$e->getMessage()]
                ];
            }
        }

        return $resultats;
    }

    /**
     * Relation avec le modèle Materiel
     */
    public function materiel()
    {
        return $this->belongsTo(Materiel::class, 'materiel_id');
    }
}
