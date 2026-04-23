<?php

namespace App\Models\Parametre;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategorieMateriel extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_categorie',
    ];

    public function materiel() {
        return $this->hasOne(Materiel::class);
    }
}
