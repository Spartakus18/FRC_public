<?php

namespace App\Models\Produit;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\AjustementStock\Stock;
use App\Models\Location\AideChauffeur;
use App\Models\Location\Conducteur;
use App\Models\Parametre\Materiel;
use App\Models\Parametre\Unite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransfertProduit extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
		'heure_depart',
		'heure_arrivee',
		'materiel_id',
        'gasoil_depart',
        'gasoil_arrive',
        'compteur_depart',
        'compteur_arrive',
        'consommation_reelle_par_heure',
        'consommation_horaire_reference',
        'ecart_consommation_horaire',
        'statut_consommation_horaire',
        'consommation_totale',
        'consommation_destination_reference',
        'ecart_consommation_destination',
        'statut_consommation_destination',
        'isDelivred',
		'chauffeur_id',
		'aideChauffeur_id',
		'remarque',
		'produit_id',
        'lieu_stockage_depart_id',
        'lieu_stockage_arrive_id',
		'quantite',
        'unite_id',
        'bon_transfert_id'
    ];

    public function materiel() {
        return $this->belongsTo(Materiel::class, 'materiel_id');
    }

    public function unite() {
        return $this->belongsTo(Unite::class, 'unite_id');
    }

    public function chauffeur() {
        return $this->belongsTo(Conducteur::class, 'chauffeur_id');
    }

    public function aideChauffeur() {
        return $this->belongsTo(AideChauffeur::class, 'aideChauffeur_id');
    }

    public function lieuStockageDepart() {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_depart_id');
    }

    public function lieuStockageArrive() {
        return $this->belongsTo(Lieu_stockage::class, 'lieu_stockage_arrive_id');
    }

    public function produit() {
        return $this->belongsTo(ArticleDepot::class, 'produit_id');
    }

    public function bonTransfert() {
        return $this->belongsTo(BonTransfert::class, 'bon_transfert_id');
    }
}