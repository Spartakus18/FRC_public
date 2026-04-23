<?php

namespace App\Models\Fourniture;

use App\Models\Historique\HistoriqueFourniture;
use App\Models\Parametre\Materiel;
use App\Models\AjustementStock\Lieu_stockage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fourniture extends Model
{
    use HasFactory;

    protected $table = 'fournitures';

    protected $fillable = [
        'nom_article',
        'reference',
        'numero_serie',
        'etat',
        'is_dispo',
        'date_acquisition',
        'materiel_id_associe',
        'autre_materiel_nom',
        'date_sortie_stock',
        'date_retour_stock',
        'localisation_actuelle',
        'commentaire',
        'lieu_stockage_id',
    ];

    protected $casts = [
        'is_dispo'          => 'boolean',
        'date_acquisition'  => 'date',
        'date_sortie_stock' => 'date',
        'date_retour_stock' => 'date',
    ];

    // ------------------------------------------------------------------------
    // RELATIONS
    // ------------------------------------------------------------------------

    /**
     * Lieu de stockage (uniquement quand la fourniture est en stock)
     */
    public function lieuStockage()
    {
        return $this->belongsTo(Lieu_stockage::class);
    }

    /**
     * Véhicule associé (via l'ancien champ materiel_id_associe)
     */
    public function materiel()
    {
        return $this->belongsTo(Materiel::class, 'materiel_id_associe');
    }

    /**
     * Historique des mouvements
     */
    public function historiques()
    {
        return $this->hasMany(HistoriqueFourniture::class, 'fourniture_id');
    }

    // ------------------------------------------------------------------------
    // ACCESSORS
    // ------------------------------------------------------------------------

    /**
     * Nom du matériel associé (véhicule ou texte libre)
     */
    public function getMaterielAssocieNomAttribute(): ?string
    {
        if ($this->materiel_id_associe) {
            return $this->materiel?->nom_materiel ?? 'Véhicule inconnu';
        }
        if ($this->autre_materiel_nom) {
            return $this->autre_materiel_nom;
        }
        return null;
    }

    /**
     * Localisation formatée pour affichage
     * - Si en stock : affiche le nom du lieu de stockage
     * - Sinon : affiche la traduction de localisation_actuelle
     */
    public function getLocalisationFormateeAttribute(): string
    {
        if ($this->lieuStockage) {
            return $this->lieuStockage->nom;
        }

        $localisations = [
            'chantier'           => 'Chantier',
            'maintenance'        => 'Maintenance',
            'atelier_maintenance' => 'Atelier maintenance',
        ];

        return $localisations[$this->localisation_actuelle]
            ?? ($this->localisation_actuelle ?? 'Non défini');
    }

    /**
     * État formaté (conservé de votre code)
     */
    public function getEtatFormateAttribute(): string
    {
        $etats = [
            'neuf'        => 'Neuf',
            'bon'         => 'Bon état',
            'moyen'       => 'État moyen',
            'a_verifier'  => 'À vérifier',
            'hors_service' => 'Hors service',
        ];

        return $etats[$this->etat] ?? $this->etat;
    }

    /**
     * Durée d'utilisation actuelle (conservé)
     */
    public function getDureeUtilisationActuelleAttribute(): int
    {
        if (!$this->is_dispo && $this->date_sortie_stock) {
            return now()->diffInDays($this->date_sortie_stock);
        }
        return 0;
    }
}
