<?php

namespace App\Models\Location;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\Parametre\Client;
use App\Models\Parametre\Materiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'client_id',
        'observation',
        'materiel_id',
        'article_id',
        'gasoil_quantite',
        'gasoil_avant',
        'jauge_debut',
        'jauge_fin',
        'heures_debut',
        'heures_fin',
        'compteur_debut',
        'compteur_fin',
        'conducteur_id',
        'facturation_unite_Id',
        'facturation_quantite',
        'facturation_prixU',
        'facturation_prixT',
    ];

    public function client () {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function materiel() {
        return $this->belongsTo(Materiel::class, 'materiel_id');
    }

    public function conducteur() {
        return $this->belongsTo(Conducteur::class, 'conducteur_id');
    }

    public function unite() {
        return $this->belongsTo(UniteFacturation::class, 'facturation_unite_Id');
    }

    public function produit() {
        return $this->belongsTo(ArticleDepot::class, 'article_id');
    }
}
