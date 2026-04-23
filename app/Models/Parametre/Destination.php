<?php

namespace App\Models\Parametre;

use App\Models\BC\Bon_commande;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_destination',
        'consommation_reference',
    ];

    public function bonCommandes() {
        return $this->hasMany(Bon_commande::class);
    }
}
