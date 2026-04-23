<?php

namespace App\Models\Location;

use App\Models\BL\BonLivraison;
use App\Models\Produit\TransfertProduit;
use App\Models\Produit\Vente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conducteur extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_conducteur',
    ];

    public function location() {
        return $this->hasMany(Location::class);
    }

    public function vente() {
        return $this->hasMany(Vente::class);
    }

    public function transfert() {
        return $this->hasMany(TransfertProduit::class);
    }

    public function BL() {
        return $this->hasOne(BonLivraison::class);
    }
}
