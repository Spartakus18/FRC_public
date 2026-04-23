<?php

namespace App\Models;

use App\Models\Parametre\Materiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompteurJournee extends Model
{
    use HasFactory;

    protected $fillable = [
        'journee_id',
        'materiel_id',
        'has_compteur',
        'compteur_matin',
        'compteur_soir',
        'variation',
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
     * Marquer has_compteur à true pour un matériel dans la journée en cours
     * seulement si has_compteur n'est pas déjà true.
     *
     * @param int $materielId
     * @param int|null $journeeId
     * @return bool
     */
    public static function markHasCompteur(int $materielId, ?int $journeeId = null): bool
    {
        if (!$journeeId) {
            $journee = Journee::journeeAujourdhui();
            if (!$journee) {
                return false;
            }
            $journeeId = $journee->id;
        }

        $compteurJournee = self::where('journee_id', $journeeId)
            ->where('materiel_id', $materielId)
            ->first();

        if ($compteurJournee && $compteurJournee->has_compteur == false) {
            $compteurJournee->update(['has_compteur' => true]);
            return true;
        }

        return false;
    }
}
