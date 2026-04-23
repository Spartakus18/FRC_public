<?php

namespace App\Models\FournitureConsommable;

use App\Models\Parametre\Unite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FournitureConsommable extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'unite_id'];

    public function unite()
    {
        return $this->belongsTo(Unite::class);
    }

    public function stocks()
    {
        return $this->hasMany(StockFourniture::class);
    }

    public function entrees()
    {
        return $this->hasMany(EntreeFourniture::class);
    }

    public function sorties()
    {
        return $this->hasMany(SortieFourniture::class);
    }
}
