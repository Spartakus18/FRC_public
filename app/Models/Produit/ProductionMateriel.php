<?php

namespace App\Models\Produit;

use App\Models\Parametre\Materiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionMateriel extends Model
{
    use HasFactory;

    protected $table = 'production_materiels';

    protected $fillable = [
        'production_id',
        'materiel_id',
        'categorie_travail_id',
        'heure_debut',
        'heure_fin',
        'compteur_debut',
        'compteur_fin',
        'gasoil_debut',
        'gasoil_fin',
        'consommation_reelle_par_heure',
        'consommation_horaire_reference',
        'ecart_consommation_horaire',
        'statut_consommation_horaire',
        'consommation_totale',
        'consommation_destination_reference',
        'ecart_consommation_destination',
        'statut_consommation_destination',
        'observation'
    ];

    public function production()
    {
        return $this->belongsTo(Produit::class, 'production_id');
    }

    public function materiel()
    {
        return $this->belongsTo(Materiel::class, 'materiel_id');
    }

    public function categorieTravail()
    {
        return $this->belongsTo(Categorie::class, 'categorie_travail_id');
    }
}
