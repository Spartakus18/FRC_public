<?php

namespace App\Models\Produit;

use App\Models\AjustementStock\Stock;
use App\Models\BL\BonLivraison;
use App\Models\Location\Conducteur;
use App\Models\Parametre\Client;
use App\Models\Parametre\Materiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vente extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'heure',
        'client_id',
        'observation',
        'materiel_id',
        'chauffeur_id',
        'destination',
        'bl_id',
        'produit_id',
        'quantite',
        'stockDispo',
        'stockApresLivraison',
    ];

    public function client() {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function vehicule() {
        return $this->belongsTo(Materiel::class, 'materiel_id');
    }

    public function chauffeur() {
        return $this->belongsTo(Conducteur::class, 'chauffeur_id');
    }

    public function produit() {
        return $this->belongsTo(Stock::class, 'produit_id');
    }

    public function bonLivraison() {
        return $this->belongsTo(BonLivraison::class, 'bl_id');
    }
}
