<?php

namespace App\Models\Parametre;

use App\Models\BC\BonGasoil;
use App\Models\BC\BonHuile;
use App\Models\BL\BonLivraison;
use App\Models\CompteurJournee;
use App\Models\Consommable\Gasoil;
use App\Models\Consommable\Huile;
use App\Models\ConsommationGasoil;
use App\Models\GasoilJournee;
use App\Models\OperationAtelierMeca;
use App\Models\Location\Location;
use App\Models\PerteGasoilOperation;
use App\Models\Produit\Produit;
use App\Models\Produit\Vente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materiel extends Model
{
    use HasFactory;

    // Table associée
    protected $table = 'materiels';

    // pour l'autorisation de modification materiel(Pour journée)
    protected static $skipJourneeCheck = false;

    // Champs remplissables
    protected $fillable = [
        'nom_materiel',
        'status',
        'categorie',
        'capaciteL', // capacité max en Litre (généralement 20L pour la référence)
        'capaciteCm', // nombre de cm que 20L de gasoil prend dans le réservoir
        'seuil', // seuil gasoil
        'consommation_horaire',
        'compteur_actuel',
        'actuelGasoil',
        'gasoil_consommation',
        'nbr_pneu',
        'seuil_notif', // Notification pour seuil
    ];

    // Casts
    protected $casts = [
        'capaciteL' => 'float',
        'capaciteCm' => 'float',
        'actuelGasoil' => 'float',
        'gasoil_consommation' => 'float',
        'consommation_horaire' => 'float',
    ];

    /*
        Méthode pour journée
    */

    /**
     * Exécuter une callback sans la vérification de la journée. POur materielObserver méthode updating
     */
    public static function withoutJourneeCheck(callable $callback)
    {
        static::$skipJourneeCheck = true;
        try {
            return $callback();
        } finally {
            static::$skipJourneeCheck = false;
        }
    }

    /**
     * Savoir si on doit ignorer la vérification.
     */
    public static function isSkippingJourneeCheck()
    {
        return static::$skipJourneeCheck;
    }

    /*
     |--------------------------------------------------------------------------
     | Méthodes de conversion
     |--------------------------------------------------------------------------
    */

    /**
     * Convertir une consommation en cm en litres
     * Formule : (consommation_cm × capaciteL) / capaciteCm
     */
    public function convertirCmEnLitres($cm)
    {
        // Vérifier que nous avons les valeurs nécessaires
        if (!$this->capaciteL || !$this->capaciteCm || $this->capaciteCm == 0) {
            // Valeur par défaut si non définie
            return $cm; // On retourne cm comme fallback
        }

        return ($cm * $this->capaciteL) / $this->capaciteCm;
    }

    /**
     * Convertir une consommation en litres en cm
     * Formule inverse : (consommation_litres × capaciteCm) / capaciteL
     */
    public function convertirLitresEnCm($litres)
    {
        if (!$this->capaciteL || !$this->capaciteCm || $this->capaciteL == 0) {
            return $litres; // On retourne litres comme fallback
        }

        return ($litres * $this->capaciteCm) / $this->capaciteL;
    }

    /**
     * Calculer la consommation en litres à partir des mesures en cm
     */
    public function calculerConsommationLitres($cmDepart, $cmArrive)
    {
        $consommationCm = $cmDepart - $cmArrive;
        return $this->convertirCmEnLitres($consommationCm);
    }

    /*
     |--------------------------------------------------------------------------
     | Relations
     |--------------------------------------------------------------------------
    */
    public function gasoilJournees()
    {
        return $this->hasMany(GasoilJournee::class);
    }

    public function compteurJournees()
    {
        return $this->hasMany(CompteurJournee::class);
    }

    public function pneus()
    {
        return $this->hasMany(Pneu::class, 'materiel_id');
    }

    public function locations()
    {
        return $this->hasMany(Location::class, 'materiel_id');
    }

    public function produits()
    {
        return $this->hasMany(Produit::class, 'materiel_id');
    }

    public function ventes()
    {
        return $this->hasMany(Vente::class, 'materiel_id');
    }


    // Gasoil reçu
    public function gasoilsCible()
    {
        return $this->hasMany(Gasoil::class, 'materiel_id_cible');
    }

    public function gasoil()
    {
        return $this->hasMany(Gasoil::class, 'materiel_id_cible');
    }


    // Huile reçu
    public function huilesCible()
    {
        return $this->hasMany(Huile::class, 'materiel_id_cible');
    }

    // BL
    public function bonLivraison()
    {
        return $this->hasMany(BonLivraison::class, 'vehicule_id');
    }

    // BC
    // Gasoil
    public function bonGasoil()
    {
        return $this->hasMany(BonGasoil::class, 'materiel_id');
    }

    // huile
    public function bonHuile()
    {
        return $this->hasMany(BonHuile::class, 'materiel_id');
    }

    // Relation avec les consommations de gasoil
    public function consommationGasoils()
    {
        return $this->hasMany(ConsommationGasoil::class, 'vehicule_id');
    }

    // Relation avec perte operation gasoil
    public function ajustementsGasoil()
    {
        return $this->hasMany(PerteGasoilOperation::class, 'materiel_id');
    }

    public function operationsAtelierMeca()
    {
        return $this->hasMany(OperationAtelierMeca::class, 'materiel_id');
    }


    /*
     |--------------------------------------------------------------------------
     | Query Scopes
     |--------------------------------------------------------------------------
     | Les scopes permettent de filtrer facilement selon la catégorie
    */

    // Scope pour véhicules
    public function scopeVehicules($query)
    {
        return $query->where('categorie', 'vehicule');
    }

    // Scope pour groupes électrogènes
    public function scopeGroupes($query)
    {
        return $query->where('categorie', 'groupe');
    }

    // Scope pour engins
    public function scopeEngins($query)
    {
        return $query->where('categorie', 'engin');
    }

    // Scope pour disponibles
    public function scopeDisponibles($query)
    {
        return $query->where('status', true);
    }

    // Scope pour indisponibles
    public function scopeIndisponibles($query)
    {
        return $query->where('status', false);
    }
}

/*
    utilisation des scop

    Récuperer les véhicule (matériel catégorie vehicul)
    $vehicules = Materiel::vehicules()->get();
*/
