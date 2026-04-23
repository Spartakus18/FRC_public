<?php

namespace App\Models\BC;

use App\Models\AjustementStock\Lieu_stockage;
use App\Models\BL\BonLivraison;
use App\Models\Parametre\Client;
use App\Models\Parametre\Destination;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bon_commande extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero',
        'date_BC',
        'client_id',
        'date_elaboration',
        'designation',
        'destination_id',
        'date_prevu_livraison',
        'observations',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function produits()
    {
        return $this->hasMany(BonCommandeProduit::class, 'bon_commande_id');
    }

    public function lieuStockage()
    {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_id');
    }

    public function bonLivraison() {
        return $this->hasMany(BonLivraison::class, 'BC_id');
    }

    public function destination() {
        return $this->belongsTo(Destination::class, 'destination_id');
    }
}
