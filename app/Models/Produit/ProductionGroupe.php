<?php

namespace App\Models\Produit;

use App\Models\Parametre\Materiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionGroupe extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_id',
        'groupe_id',
        'categorie_travail_id',
        'heure_debut',
        'heure_fin',
        'compteur_debut',
        'compteur_fin',
        'gasoil_debut',
        'gasoil_fin',
        'observation'
    ];

    public function production() {
        return $this->belongsTo(Produit::class, 'production_id');
    }

    public function groupe() {
        return $this->belongsTo(Materiel::class, 'groupe_id');
    }

    public function categorieTravail() {
        return $this->belongsTo(Categorie::class, 'categorie_travail_id');
    }
}
