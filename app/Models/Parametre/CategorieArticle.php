<?php

namespace App\Models\Parametre;

use App\Models\AjustementStock\ArticleDepot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategorieArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_categorie',
    ];

    public function article() {
        return $this->hasMany(ArticleDepot::class, 'categorie_id');
    }
}
