<?php

namespace App\Models\Historique;

use App\Models\Fourniture\Fourniture;
use App\Models\Parametre\Materiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoriqueFourniture extends Model
{
    use HasFactory;

    protected $table = 'historique_fournitures';

    protected $fillable = [
        'fourniture_id',
        'ancien_materiel_id',
        'ancien_materiel_nom',
        'nouveau_materiel_id',
        'nouveau_materiel_nom',
        'type_action',
        'date_action',
        'commentaire',
        'etat',
    ];

    protected $casts = [
        'date_action' => 'datetime',
    ];

    /**
     * Un historique appartient toujours à une fourniture.
     */
    public function fourniture()
    {
        return $this->belongsTo(Fourniture::class, 'fourniture_id');
    }

    /**
     * Optionnel : accéder directement aux anciens/nouveaux matériels
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