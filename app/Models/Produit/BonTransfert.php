<?php

namespace App\Models\Produit;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\Parametre\Unite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonTransfert extends Model
{
    use HasFactory;

    protected $table = "bon_transferts";

    protected $fillable = [
        'numero_bon',
        'date_transfert',
        'produit_id',
        'quantite',
        'unite_id',
        'lieu_stockage_depart_id',
        'lieu_stockage_arrive_id',
        'commentaire',
        'user_id',
        'est_utilise'
    ];

    protected $casts = [
        'date_transfert' => 'date',
        'est_utilise' => 'boolean'
    ];

    public function produit()
    {
        return $this->belongsTo(ArticleDepot::class, 'produit_id');
    }

    public function unite()
    {
        return $this->belongsTo(Unite::class, 'unite_id');
    }

    public function lieuStockageDepart()
    {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_depart_id');
    }

    public function lieuStockageArrive()
    {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_arrive_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transferts()
    {
        return $this->hasMany(TransfertProduit::class, 'bon_transfert_id');
    }
}
