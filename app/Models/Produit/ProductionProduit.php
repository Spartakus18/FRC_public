<?php

namespace App\Models\Produit;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\Parametre\Unite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionProduit extends Model
{
    use HasFactory;

    protected $table = 'production_produits';

    protected $fillable = [
        'production_id',
        'produit_id',
        'quantite',
        'unite_id',
        'lieu_stockage_id',
        'observation',
    ];

    public function production()
    {
        return $this->belongsTo(Produit::class, 'production_id');
    }

    public function articleDepot()
    {
        return $this->belongsTo(ArticleDepot::class, 'produit_id');
    }

    public function uniteProduction()
    {
        return $this->belongsTo(Unite::class, 'unite_id');
    }

    public function uniteLivraison()
    {
        return $this->hasMany(Unite::class, 'unite_livraison_id');
    }

    public function lieuStockage()
    {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_id');
    }
}
