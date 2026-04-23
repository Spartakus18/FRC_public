<?php

namespace App\Models\FournitureConsommable;

use App\Models\AjustementStock\Lieu_stockage;
use App\Models\Parametre\Unite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntreeFourniture extends Model
{
    use HasFactory;

    protected $table = 'entree_fournitures';

    protected $fillable = [
        'user_name',
        'fourniture_id',
        'lieu_stockage_id',
        'unite_id',
        'quantite',
        'prix_unitaire',
        'prix_total',
        'motif',
        'entre',
    ];

    protected $casts = [
        'entre' => 'date',
    ];

    public function fourniture()
    {
        return $this->belongsTo(FournitureConsommable::class, 'fourniture_id');
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
