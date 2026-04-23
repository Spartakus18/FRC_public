<?php

namespace App\Models\Parametre;

use App\Models\Consommable\Huile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'categorie_id',
        'designation',
        'unite_production_id',
        'unite_livraison_id',
        'quantite_max_livraison',
    ];

    public function categorieArticle() {
        return $this->belongsTo(CategorieArticle::class, 'categorie_id');
    }

    public function uniteProduction() {
        return $this->belongsTo(Unite::class, 'unite_production_id');
    }

    public function uniteLivraison() {
        return $this->belongsTo(Unite::class, 'unite_livraison_id');
    }

    // logique : cette article est présent dans plusier versement/transfert d'huile
    public function huile()
    {
        return $this->hasMany(Huile::class, 'article_id');
    }
}
