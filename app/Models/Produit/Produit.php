<?php

namespace App\Models\Produit;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\BL\BonLivraison;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produit extends Model
{
    use HasFactory;

    protected $fillable = [
        'date_prod',
        'heure_debut',
        'heure_fin',
        'isProduct',
        'remarque',
        'user_id',
        'create_user_id',
        'update_user_id',
    ];


    public function materiels()
    {
        return $this->hasMany(ProductionMateriel::class, 'production_id');
    }

    public function produits()
    {
        return $this->hasMany(ProductionProduit::class, 'production_id');
    }

    public function categorieTravail()
    {
        return $this->belongsTo(Categorie::class, 'categorie_travail_id');
    }

    public function articleDepot()
    {
        return $this->belongsTo(ArticleDepot::class);
    }

    public function userCreate()
    {
        return $this->belongsTo(User::class, 'create_user_id');
    }

    public function userUpdate()
    {
        return $this->belongsTo(User::class, 'update_user_id');
    }
}
