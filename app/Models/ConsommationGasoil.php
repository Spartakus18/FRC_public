<?php

namespace App\Models;

use App\Models\BL\BonLivraison;
use App\Models\Parametre\Destination;
use App\Models\Parametre\Materiel;
use App\Models\Produit\ProductionMateriel;
use App\Models\Produit\TransfertProduit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ConsommationGasoil extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicule_id',
        'quantite',
        'distance_km',
        'date_consommation',
        'consommation_reelle_par_heure',
        'consommation_horaire_reference',
        'ecart_consommation_horaire',
        'statut_consommation_horaire',
        'consommation_totale',
        'consommation_destination_reference',
        'ecart_consommation_destination',
        'statut_consommation_destination',
        'bon_livraison_id',
        'destination_id',
        'transfert_produit_id',
        'production_materiel_id',
        'operation_vehicule_id'
    ];

    public function transfertProduit() {
        return $this->belongsTo(TransfertProduit::class, 'transfert_produit_id');
    }

    public function productionMateriel() {
        return $this->belongsTo(ProductionMateriel::class, 'production_materiel_id');
    }

    public function bonLivraison() {
        return $this->belongsTo(BonLivraison::class, 'bon_livraison_id');
    }

    public function destination() {
        return $this->belongsTo(Destination::class, 'destination_id');
    }

    public function operationVehicule(){
        return $this->belongsTo(OperationVehicule::class, 'operation_vehicule_id');
    }

    public function vehicule()
    {
        return $this->belongsTo(Materiel::class, 'vehicule_id');
    }
}

