<?php

namespace App\Models\Gasoil;

use App\Models\Huile\Versement;
use App\Models\Huile\Vidange;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterielFusion extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_materiel',
    ];

    public function tranfert() {
        return $this->hasMany(Transfert::class);
    }

    public function versementGasoil() {
        return $this->hasMany(Versement::class);
    }

    public function versementHuile() {
        return $this->hasMany(Versement::class);
    }

    public function vidange() {
        return $this->hasMany(Vidange::class);
    }
}
