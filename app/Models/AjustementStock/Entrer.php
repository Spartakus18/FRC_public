<?php

namespace App\Models\AjustementStock;

use App\Models\Parametre\CategorieArticle;
use App\Models\Parametre\Unite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entrer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_name',
        'article_id',
        'categorie_article_id',
        'lieu_stockage_id',
        'quantite',
        'unite_id',
        'prix_unitaire',
        'prix_total',
        'motif',
        'entre',
    ];

    public function article() {
        return $this->belongsTo(ArticleDepot::class, 'article_id');
    }

    public function categorieArticle() {
        return $this->belongsTo(CategorieArticle::class, 'categorie_article_id');
    }

    public function lieuStockage() {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_id');
    }

    public function unite() {
        return $this->belongsTo(Unite::class, 'unite_id');
    }
}
