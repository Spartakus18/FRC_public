<?php

namespace App\Models\BL;

use App\Models\BC\Bon_commande;
use App\Models\BC\BonCommandeProduit;
use App\Models\Location\AideChauffeur;
use App\Models\Location\Conducteur;
use App\Models\Location\MaterielLocation;
use App\Models\Parametre\Client;
use App\Models\Parametre\Materiel;
use App\Models\Produit\TransfertProduit;
use App\Models\Produit\Vente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonLivraison extends Model
{
    use HasFactory;

    protected $fillable = [
        'numBL',
        'heure_depart',
        'heure_arrive',
        'vehicule_id',
        'gasoil_depart',
        'gasoil_arrive',
        'compteur_depart',
        'compteur_arrive',
        'nbr_voyage',
        'quantite',
        'quantite_deja_livree',
        'heure_machine',
        'consommation_reelle_par_heure',
        'consommation_horaire_reference',
        'ecart_consommation_horaire',
        'statut_consommation_horaire',
        'consommation_totale',
        'consommation_destination_reference',
        'ecart_consommation_destination',
        'statut_consommation_destination',
        'date_arriver',
        'isDelivred',
        'date_livraison',
        'chauffeur_id',
        'heure_chauffeur',
        'aide_chauffeur_id',
        'bon_commande_produit_id',
        'client_id',
        'PU',
        'remarque',
    ];

    public function chauffeur() {
        return $this->belongsTo(Conducteur::class, 'chauffeur_id');
    }

    public function vehicule() {
        return $this->belongsTo(Materiel::class, 'vehicule_id');
    }

    public function aideChauffeur() {
        return $this->belongsTo(AideChauffeur::class, 'aide_chauffeur_id');
    }

    public function bonCommandeProduit() {
        return $this->belongsTo(BonCommandeProduit::class, 'bon_commande_produit_id');
    }

    public function bonCommande() {
        return $this->belongsTo(Bon_commande::class, 'BC_id');
    }

    public function client() {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function vente() {
        return $this->hasMany(Vente::class);
    }

    public function TransfertProduit() {
        return $this->hasOne(TransfertProduit::class);
    }
}
