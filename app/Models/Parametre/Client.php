<?php

namespace App\Models\Parametre;

use App\Models\BC\Bon_commande;
use App\Models\BL\BonLivraison;
use App\Models\Location\Location;
use App\Models\Produit\Vente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_client',
    ];

    public function location() {
        return $this->hasMany(Location::class);
    }
    public function vente() {
        return $this->hasMany(Vente::class);
    }
    public function bonCommande() {
        return $this->hasMany(Bon_commande::class);
    }

    public function BL() {
        return $this->hasOne(BonLivraison::class);
    }
}
