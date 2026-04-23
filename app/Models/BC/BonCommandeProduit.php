<?php

namespace App\Models\BC;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\Parametre\Unite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonCommandeProduit extends Model
{
    use HasFactory;

    protected $fillable = [
        'bon_commande_id',
        'article_id',
        'lieu_stockage_id',
        'unite_id',
        'quantite',
        'pu',
        'montant',
    ];

    public function article() {
        return $this->belongsTo(ArticleDepot::class, 'article_id');
    }

    public function unite() {
        return $this->belongsTo(Unite::class, 'unite_id');
    }

    public function bonCommande() {
        return $this->belongsTo(Bon_commande::class, 'bon_commande_id');
    }

    public function lieuStockage()
    {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_id');
    }
}
