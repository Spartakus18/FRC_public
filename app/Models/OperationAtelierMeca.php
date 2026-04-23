<?php

namespace App\Models;

use App\Models\AjustementStock\Stock;
use App\Models\Parametre\Materiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationAtelierMeca extends Model
{
    use HasFactory;

    protected $table = 'operation_atelier_mecas';

    protected $fillable = [
        'materiel_id',
        'stock_id',
        'gasoil_retirer',
        'quantite_retiree_cm',
        'reste_gasoil',
        'stock_atelier_avant',
        'stock_atelier_apres',
        'is_remis',
        'remis_materiel_id',
        'remis_user_id',
        'remis_at',
        'commentaire',
        'user_id',
        'operation_at',
    ];

    protected $casts = [
        'gasoil_retirer' => 'float',
        'quantite_retiree_cm' => 'float',
        'reste_gasoil' => 'float',
        'stock_atelier_avant' => 'float',
        'stock_atelier_apres' => 'float',
        'is_remis' => 'boolean',
        'remis_at' => 'datetime',
        'operation_at' => 'datetime',
    ];

    public function materiel()
    {
        return $this->belongsTo(Materiel::class, 'materiel_id');
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class, 'stock_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function remisMateriel()
    {
        return $this->belongsTo(Materiel::class, 'remis_materiel_id');
    }

    public function remisUser()
    {
        return $this->belongsTo(User::class, 'remis_user_id');
    }
}
