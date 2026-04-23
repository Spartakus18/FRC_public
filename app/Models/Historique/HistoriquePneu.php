<?php

namespace App\Models\Historique;

use App\Models\Parametre\Materiel;
use App\Models\Parametre\Pneu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoriquePneu extends Model
{
    use HasFactory;

    protected $fillable = [
        'pneu_id',
        'ancien_materiel_id',
        'ancien_materiel_nom',
        'nouveau_materiel_id',
        'nouveau_materiel_nom',
        'type_action',
        'date_action',
        'commentaire',
    ];

    /**
     * Un historique appartient toujours à un pneu.
     */
    public function pneu()
    {
        return $this->belongsTo(Pneu::class, 'pneu_id');
    }

    /**
     * Récupère le matériel actuel du pneu via sa relation.
     */
    public function materielActuel()
    {
        return $this->pneu->materiel;
    }

    /**
     * Optionnel : accéder directement aux anciens/nouveaux matériels
     * (si jamais tu veux relier avec la table materiels).
     */
    public function ancienMateriel()
    {
        return $this->belongsTo(Materiel::class, 'ancien_materiel_id');
    }

    public function nouveauMateriel()
    {
        return $this->belongsTo(Materiel::class, 'nouveau_materiel_id');
    }
}
