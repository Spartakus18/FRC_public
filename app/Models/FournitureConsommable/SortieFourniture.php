<?php

namespace App\Models\FournitureConsommable;

use App\Models\AjustementStock\Lieu_stockage;
use App\Models\Parametre\Unite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SortieFourniture extends Model
{
    use HasFactory;

    protected $table = 'sortie_fournitures';

    protected $fillable = [
        'user_name',
        'fourniture_id',
        'lieu_stockage_id',
        'unite_id',
        'quantite',
        'demande_par',
        'sortie_par',
        'motif',
        'sortie',
    ];

    protected $casts = [
        'sortie' => 'date',
    ];

    public function fourniture()
    {
        return $this->belongsTo(FournitureConsommable::class);
    }

    public function lieuStockage()
    {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_id');
    }

    public function unite()
    {
        return $this->belongsTo(Unite::class);
    }
}
