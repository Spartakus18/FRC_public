<?php

namespace App\Models\FournitureConsommable;

use App\Models\AjustementStock\Lieu_stockage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockFourniture extends Model
{
    use HasFactory;

    protected $table = 'stock_fournitures';

    protected $fillable = ['fourniture_id', 'lieu_stockage_id', 'quantite'];

    public function fourniture()
    {
        return $this->belongsTo(FournitureConsommable::class, 'fourniture_id');
    }

    public function lieuStockage()
    {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_id');
    }
}
