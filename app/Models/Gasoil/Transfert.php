<?php

namespace App\Models\Gasoil;

use App\Models\Location\Vehicule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfert extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
		'bon',
        'vehicule_id',
		'tranfertDe',
		'transfereA',
		'quantite',
    ];

    public function materiel() {
        return $this->belongsTo(MaterielFusion::class);
    }

    public function vehicule()
    {
        return $this->belongsTo(Vehicule::class, 'vehicule_id');
    }
}
