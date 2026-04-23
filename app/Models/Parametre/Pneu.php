<?php

namespace App\Models\Parametre;

use App\Models\AjustementStock\Lieu_stockage;
use App\Models\Historique\HistoriquePneu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pneu extends Model
{
    use HasFactory;

    protected $fillable = [
        'date_obtention',
        'date_mise_en_service',
        'date_mise_hors_service',
        'etat',
        'caracteristiques',
        'marque',
        'num_serie',
        'type',
        'situation',
        'emplacement',
        'observations',
        'kilometrage',
        'materiel_id',
        'lieu_stockages_id'
    ];

    public function materiel()
    {
        return $this->belongsTo(Materiel::class, 'materiel_id');
    }

    /**
     * Un pneu peut avoir plusieurs historiques.
     */
    public function historiques()
    {
        return $this->hasMany(HistoriquePneu::class, 'pneu_id');
    }

    public function lieuStockage()
    {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockages_id');
    }
}
