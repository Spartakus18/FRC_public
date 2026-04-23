<?php

namespace App\Models\Gasoil;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Versement extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
		'bon',
		'source_id',
		'observation',
		'materiel_id',
		'quantite',
    ];

    public function source() {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function materiel() {
        return $this->belongsTo(MaterielFusion::class, 'materiel_id');
    }
}
