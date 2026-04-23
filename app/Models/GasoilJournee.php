<?php

namespace App\Models;

use App\Models\Consommable\Gasoil;
use App\Models\Parametre\Materiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GasoilJournee extends Model
{
    protected $fillable = [
        'journee_id',
        'materiel_id',
        'has_gasoil',
        'gasoil_matin',
        'gasoil_soir',
        'consommation',
    ];

    public function journee()
    {
        return $this->belongsTo(Journee::class);
    }

    public function materiel()
    {
        return $this->belongsTo(Materiel::class);
    }

    /**
     * Marquer has_gasoil à true pour un matériel dans la journée en cours
     * Seulement si has_gasoil n'est pas déjà true
     *
     * @param int $materielId
     * @param int|null $journeeId
     * @return bool
     */
    public static function markHasGasoil(int $materielId, ?int $journeeId = null): bool
    {
        // Si aucune journée n'est spécifiée, utiliser la journée d'aujourd'hui
        if (!$journeeId) {
            $journee = Journee::journeeAujourdhui();
            if (!$journee) {
                return false;
            }
            $journeeId = $journee->id;
        }

        // Chercher l'enregistrement GasoilJournee pour ce matériel et cette journée
        $gasoilJournee = self::where('journee_id', $journeeId)
            ->where('materiel_id', $materielId)
            ->first();

        // Si l'enregistrement existe et que has_gasoil est false, le mettre à true
        if ($gasoilJournee && $gasoilJournee->has_gasoil == false) {
            $gasoilJournee->update(['has_gasoil' => true]);
            return true;
        }

        return false;
    }
}
