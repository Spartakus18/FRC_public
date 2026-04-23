<?php

namespace App\Models\Consommable;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\BC\BonHuile;
use App\Models\Huile\ArticleVersement;
use App\Models\Huile\Subdivision;
use App\Models\Parametre\Materiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Huile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bon_id',
        'source_station',
        'source_lieu_stockage_id',
        'quantite',
        'quantite_stock_avant',
        'quantite_stock_apres',
        'prix_total',
        'materiel_id_cible',
        'subdivision_id_cible',
        'type_operation',
        'materiel_id_source',
        'subdivision_id_source',
        'article_versement_id',
        'is_consumed',
        'ajouter_par',
        'modifier_par',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function bon()
    {
        return $this->belongsTo(BonHuile::class, 'bon_id');
    }

    public function materielSource()
    {
        return $this->belongsTo(Materiel::class, 'materiel_id_source');
    }

    public function subdivisionSource()
    {
        return $this->belongsTo(Subdivision::class, 'subdivision_id_source');
    }

    public function materielCible()
    {
        return $this->belongsTo(Materiel::class, 'materiel_id_cible');
    }

    public function subdivisionCible()
    {
        return $this->belongsTo(Subdivision::class, 'subdivision_id_cible');
    }

    public function articleDepot()
    {
        return $this->belongsTo(ArticleDepot::class, 'article_versement_id');
    }


    // pour le source
    public function sourceLieuStockage()
    {
        return $this->belongsTo(Lieu_stockage::class, 'source_lieu_stockage_id');
    }
}
