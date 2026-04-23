<?php

namespace App\Models\Location;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniteFacturation extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_unite'
    ];

    public function location() {
        return $this->hasMany(Location::class);
    }
}
