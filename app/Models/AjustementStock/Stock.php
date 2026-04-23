<?php

namespace App\Models\AjustementStock;

use App\Models\BC\Bon_commande;
use App\Models\Parametre\CategorieArticle;
use App\Models\Produit\Vente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'lieu_stockage_id',
        'quantite',
        'categorie_article_id',
        'isAtelierMeca',
    ];

    protected $casts = [
        'isAtelierMeca' => 'boolean',
        'quantite' => 'float',
    ];

    public function vente()
    {
        return $this->hasMany(Vente::class);
    }

    public function sortie()
    {
        return $this->hasMany(Sortie::class);
    }

    public function articleDepot()
    {
        return $this->belongsTo(ArticleDepot::class, 'article_id');
    }

    public function categorieArticle() {
        return $this->belongsTo(CategorieArticle::class, 'categorie_article_id');
    }

    public function lieuStockage()
    {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_id');
    }

    public function bonCommande()
    {
        return $this->belongsTo(Bon_commande::class);
    }

    public function operationsAtelierMeca()
    {
        return $this->hasMany(\App\Models\OperationAtelierMeca::class, 'stock_id');
    }
}
