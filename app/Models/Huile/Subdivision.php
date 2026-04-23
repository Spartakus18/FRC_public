<?php

namespace App\Models\Huile;

use App\Models\BC\BonHuile;
use App\Models\Consommable\Huile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subdivision extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_subdivision',
    ];

    // subdivision source d'huile
    public function subdivisionSource()
    {
        return $this->hasMany(Huile::class, "subdivision_id_source");
    }
    // subdivision cible pour le versement/transfert
    public function subdivisionCible()
    {
        return $this->hasMany(Huile::class, 'subdivision_id_cible');
    }

    // BC
    public function bonHuile()
    {
        return $this->hasMany(BonHuile::class, 'subdivisions_id');
    }
}
