<?php

namespace App\Models\AjustementStock;

use App\Models\BC\Bon_commande;
use App\Models\Consommable\Huile;
use App\Models\Location\Location;
use App\Models\Parametre\CategorieArticle;
use App\Models\Parametre\Unite;
use App\Models\Produit\ProductionProduit;
use App\Models\Produit\Produit;
use App\Models\Produit\TransfertProduit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleDepot extends Model
{
    use HasFactory;

    protected $table = 'article_depots';

    protected $fillable = [
        'nom_article',
        'unite_production_id',
        'unite_livraison_id',
        'categorie_id',
        'designation'
    ];

    public function uniteProduction()
    {
        return $this->belongsTo(Unite::class, 'unite_production_id');
    }

    public function uniteLivraison()
    {
        return $this->belongsTo(Unite::class, 'unite_livraison_id');
    }

    public function categorie()
    {
        return $this->belongsTo(CategorieArticle::class, 'categorie_id');
    }

    public function nouvel()
    {
        return $this->hasMany(Produit::class);
    }

    public function transfert()
    {
        return $this->hasMany(TransfertProduit::class);
    }

    public function commande()
    {
        return $this->hasMany(Bon_commande::class);
    }

    public function location()
    {
        return $this->hasMany(Location::class);
    }

    public function entrer()
    {
        return $this->hasMany(Entrer::class);
    }

    public function sortie()
    {
        return $this->hasMany(Sortie::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function productionProduit()
    {
        return $this->hasMany(ProductionProduit::class, 'produit_id');
    }

    // pour l'ajout d'un huile
    public function huile()
    {
        return $this->hasMany(Huile::class, 'article_versement_id');
    }

    public function getTotalQuantiteAttribute()
    {
        return $this->stocks()->sum('quantite');
    }

    // NOUVELLE MÉTHODE: Récupérer l'unité de livraison
    public function getUniteLivraisonAttribute()
    {
        return $this->uniteLivraison()->first();
    }

    // NOUVELLE MÉTHODE: Récupérer le nom de l'unité de livraison
    public function getNomUniteLivraisonAttribute()
    {
        $unite = $this->uniteLivraison()->first();
        return $unite ? $unite->nom_unite : null;
    }

    // NOUVELLE MÉTHODE: Vérifier si l'article a une quantité maximale de livraison
    public function hasQuantiteMaxLivraison()
    {
        return !is_null($this->quantite_max_livraison) && $this->quantite_max_livraison > 0;
    }

    // NOUVELLE MÉTHODE: Obtenir la quantité maximale de livraison
    public function getQuantiteMaxLivraisonAttribute($value)
    {
        return $value ?? 0;
    }

    // NOUVELLE MÉTHODE: Formater la quantité maximale de livraison pour l'affichage
    public function getQuantiteMaxLivraisonFormattedAttribute()
    {
        if (!$this->quantite_max_livraison) {
            return null;
        }

        return [
            'valeur' => $this->quantite_max_livraison,
            'unite' => $this->nom_unite_livraison,
            'texte' => "Max: {$this->quantite_max_livraison} {$this->nom_unite_livraison}"
        ];
    }
}
