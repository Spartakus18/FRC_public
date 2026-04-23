<?php

namespace App\Models;

use App\Models\Consommable\Gasoil;
use App\Models\Parametre\Materiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerteGasoilOperation extends Model
{
    use HasFactory;

    protected $table = 'perte_gasoil_operations';

    protected $fillable = [
        'gasoil_avant',
        'gasoil_apres',
        'gasoil_id',
        'motif',
        'user_id',
        'materiel_id'
    ];

    public function gasoil()
    {
        return $this->belongsTo(Gasoil::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function materiel()
    {
        return $this->belongsTo(Materiel::class);
    }

}
