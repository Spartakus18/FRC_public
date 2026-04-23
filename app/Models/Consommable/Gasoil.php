<?php

namespace App\Models\Consommable;

use App\Models\AjustementStock\Lieu_stockage;
use App\Models\BC\BonGasoil;
use App\Models\BC\BonHuile;
use App\Models\GasoilJournee;
use App\Models\Parametre\Materiel;
use App\Models\PerteGasoilOperation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gasoil extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bon_id',
        'source_station',
        'source_lieu_stockage_id',
        'quantite',
        'quantite_stock_avant',
        'quantite_stock_apres',
        'prix_gasoil',
        'type_operation',
        'materiel_id_source',
        'materiel_id_cible',
        'ajouter_par',
        'modifier_par',
        'materiel_go_avant',
        'materiel_go_apres',
        'is_consumed'
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */
    public function gasoilJournees()
    {
        return $this->hasMany(GasoilJournee::class);
    }

    public function bon()
    {
        return $this->belongsTo(BonGasoil::class, 'bon_id');
    }

    public function materielSource()
    {
        return $this->belongsTo(Materiel::class, 'materiel_id_source');
    }

    public function materielCible()
    {
        return $this->belongsTo(Materiel::class, 'materiel_id_cible');
    }

    // pour source
    public function source()
    {
        return $this->belongsTo(Lieu_stockage::class, 'source_lieu_stockage_id');
    }

    public function perteOperation()
    {
        return $this->hasOne(PerteGasoilOperation::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Hooks - Calcul automatique
    |--------------------------------------------------------------------------
    */
    protected static function booted()
    {
        static::saving(function ($gasoil) {
            $gasoil->prix_total = $gasoil->quantite && $gasoil->prix_gasoil
                ? $gasoil->quantite * $gasoil->prix_gasoil
                : null;
        });
    }
}
