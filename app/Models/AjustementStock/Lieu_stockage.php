<?php

namespace App\Models\AjustementStock;

use App\Models\BC\Bon_commande;
use App\Models\BC\BonCommandeProduit;
use App\Models\Consommable\Gasoil;
use App\Models\Consommable\Huile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lieu_stockage extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'heure_chauffeur',
    ];

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function bonCommande()
    {
        return $this->hasMany(Bon_commande::class);
    }

    public function produits()
    {
        return $this->hasMany(BonCommandeProduit::class);
    }

    //lieu stockage
    public function gasoilVersement()
    {
        return $this->hasMany(Gasoil::class, 'source_lieu_stockage_id');
    }

    public function huileVersement()
    {
        return $this->hasMany(Huile::class, 'source_lieu_stockage_id');
    }
}
