<?php

namespace App\Models\Parametre;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubventionMateriel extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_subvention',
    ];
}
