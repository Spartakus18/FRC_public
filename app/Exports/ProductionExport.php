<?php

namespace App\Exports;

use App\Models\Produit\Produit;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductionExport implements FromCollection, WithHeadings, WithMapping
{
    protected $filters;
    protected $isAdmin;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
        $this->isAdmin = Auth::check() && Auth::user()->role_id === 1;
    }

    public function collection()
    {
        $query = Produit::with([
            'produits.articleDepot', // La relation 'produits' dans le modèle Produit (ProductionProduit)
            'produits.uniteProduction', // Relation correcte
            'produits.lieuStockage',
            'materiels.materiel',
            'materiels.categorieTravail',
            'userCreate',
            'userUpdate',
        ]);

        // Appliquer les filtres (comme ton index())
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('remarque', 'like', "%$search%")
                  ->orWhereHas('produits.articleDepot', function($q2) use ($search) {
                      $q2->where('nom_article', 'like', "%$search%");
                  })
                  ->orWhereHas('materiels.materiel', function($q2) use ($search) {
                      $q2->where('nom_materiel', 'like', "%$search%");
                  });
            });
        }

        if (!empty($this->filters['date_start'])) {
            $query->whereDate('date_prod', '>=', $this->filters['date_start']);
        }

        if (!empty($this->filters['date_end'])) {
            $query->whereDate('date_prod', '<=', $this->filters['date_end']);
        }

        if (!empty($this->filters['produit_id'])) {
            $query->whereHas('produits', function($q) {
                $q->where('produit_id', $this->filters['produit_id']);
            });
        }

        if (!empty($this->filters['lieu_stockage_id'])) {
            $query->whereHas('produits', function($q) {
                $q->where('lieu_stockage_id', $this->filters['lieu_stockage_id']);
            });
        }

        return $query->orderBy('date_prod', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Type',
            'ID Production',
            'Date production',
            'Heure début',
            'Heure fin',
            'Nom',
            'Catégorie travail',
            'Quantité',
            'Unité',
            'Lieu de stockage',
            'Gasoil début',
            'Gasoil fin',
            'Consommation totale',
            'Heure début (spécifique)',
            'Heure fin (spécifique)',
            'Compteur début',
            'Compteur fin',
            'Consommation/h',
            'Consommation réf/h',
            'Écart consommation',
            'Statut consommation',
            'Observation spécifique',
            'Remarque générale',
        ];
    }

    public function map($production): array
    {
        $rows = [];
        
        // 1. Exporter les PRODUITS (articles produits)
        foreach ($production->produits as $produitItem) {
            $rows[] = [
                'Type' => 'Produit',
                'ID Production' => $production->id,
                'Date production' => $production->date_prod,
                'Heure début' => $production->heure_debut,
                'Heure fin' => $production->heure_fin,
                'Nom' => $produitItem->articleDepot->nom_article ?? null,
                'Catégorie travail' => null,
                'Quantité' => $produitItem->quantite,
                'Unité' => $produitItem->uniteProduction->nom_unite ?? null,
                'Lieu de stockage' => $produitItem->lieuStockage->nom_lieu ?? null,
                'Gasoil début' => null,
                'Gasoil fin' => null,
                'Consommation totale' => null,
                'Heure début (spécifique)' => null,
                'Heure fin (spécifique)' => null,
                'Compteur début' => null,
                'Compteur fin' => null,
                'Consommation/h' => null,
                'Consommation réf/h' => null,
                'Écart consommation' => null,
                'Statut consommation' => null,
                'Observation spécifique' => $produitItem->observation ?? null,
                'Remarque générale' => $this->isAdmin ? $production->remarque : null,
            ];
        }
        
        // 2. Exporter les MATÉRIELS (matériels utilisés)
        foreach ($production->materiels as $materiel) {
            $rows[] = [
                'Type' => 'Matériel',
                'ID Production' => $production->id,
                'Date production' => $production->date_prod,
                'Heure début' => $production->heure_debut,
                'Heure fin' => $production->heure_fin,
                'Nom' => $materiel->materiel->nom_materiel ?? null,
                'Catégorie travail' => $materiel->categorieTravail->nom_categorie ?? null,
                'Quantité' => null,
                'Unité' => null,
                'Lieu de stockage' => null,
                'Gasoil début' => $materiel->gasoil_debut,
                'Gasoil fin' => $materiel->gasoil_fin,
                'Consommation totale' => $materiel->consommation_totale,
                'Heure début (spécifique)' => $materiel->heure_debut,
                'Heure fin (spécifique)' => $materiel->heure_fin,
                'Compteur début' => $materiel->compteur_debut,
                'Compteur fin' => $materiel->compteur_fin,
                'Consommation/h' => $materiel->consommation_reelle_par_heure,
                'Consommation réf/h' => $materiel->consommation_horaire_reference,
                'Écart consommation' => $materiel->ecart_consommation_horaire,
                'Statut consommation' => $materiel->statut_consommation_horaire,
                'Observation spécifique' => $materiel->observation ?? null,
                'Remarque générale' => $this->isAdmin ? $production->remarque : null,
            ];
        }
        
        // Si aucune donnée, retourner une ligne vide avec les infos de base
        if (count($rows) === 0) {
            $rows[] = [
                'Type' => 'Production',
                'ID Production' => $production->id,
                'Date production' => $production->date_prod,
                'Heure début' => $production->heure_debut,
                'Heure fin' => $production->heure_fin,
                'Nom' => null,
                'Catégorie travail' => null,
                'Quantité' => null,
                'Unité' => null,
                'Lieu de stockage' => null,
                'Gasoil début' => null,
                'Gasoil fin' => null,
                'Consommation totale' => null,
                'Heure début (spécifique)' => null,
                'Heure fin (spécifique)' => null,
                'Compteur début' => null,
                'Compteur fin' => null,
                'Consommation/h' => null,
                'Consommation réf/h' => null,
                'Écart consommation' => null,
                'Statut consommation' => null,
                'Observation spécifique' => null,
                'Remarque générale' => $this->isAdmin ? $production->remarque : null,
            ];
        }
        
        return $rows;
    }
    
    // Méthode pour gérer les lignes multiples
    public function query()
    {
        return $this->collection();
    }
}