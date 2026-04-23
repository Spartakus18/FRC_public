<?php

namespace App\Models\Gasoil;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_source',
    ];

    public function versement() {
        return $this->hasMany(Versement::class);
    }
}
