<?php

namespace App\Models\Produit;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\Parametre\Unite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MelangeProduit extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'produit_a_id',
        'produit_b_id',
        'lieu_stockage_a_id',
        'lieu_stockage_b_id',
        'lieu_stockage_final_id',
        'unite_livraison_id',
        'quantite_a',
        'quantite_b_consommee',
        'quantite_b_produite',
        'observation',
    ];

    public function produitA()
    {
        return $this->belongsTo(ArticleDepot::class, 'produit_a_id');
    }

    public function produitB()
    {
        return $this->belongsTo(ArticleDepot::class, 'produit_b_id');
    }

    public function uniteLivraison()
    {
        return $this->belongsTo(Unite::class, 'unite_livraison_id');
    }

    public function lieuStockageA()
    {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_a_id');
    }

    public function lieuStockageB()
    {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_b_id');
    }

    public function lieuStockageFinal()
    {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_final_id');
    }

    // Accessor pour l'augmentation nette
    public function getAugmentationAttribute()
    {
        return $this->quantite_b_produite - $this->quantite_b_consommee;
    }
}