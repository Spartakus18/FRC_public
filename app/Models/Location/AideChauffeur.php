<?php

namespace App\Models\Location;

use App\Models\BL\BonLivraison;
use App\Models\Produit\TransfertProduit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AideChauffeur extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_aideChauffeur',
    ];

    public function BL() {
        return $this->hasOne(BonLivraison::class);
    }

    public function transfert() {
        return $this->hasMany(TransfertProduit::class);
    }
}
