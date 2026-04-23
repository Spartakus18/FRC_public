<?php

namespace App\Exports;

use App\Models\Produit\Vente;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VentesExport implements FromCollection, WithHeadings
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
        $query = Vente::with([
            'client',
            'vehicule',
            'chauffeur',
            'produit.articleDepot',
            'produit.articleDepot.uniteLivraison',
            'produit.lieuStockage',
            'bonLivraison'
        ]);

        // Filtres similaires à l'index
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('observation', 'like', "%$search%")
                  ->orWhere('destination', 'like', "%$search%")
                  ->orWhereHas('client', fn($q2) => $q2->where('nom_client', 'like', "%$search%"))
                  ->orWhereHas('produit.articleDepot', fn($q3) => $q3->where('nom_article', 'like', "%$search%"));
            });
        }

        if (!empty($this->filters['date_start'])) {
            $query->whereDate('date', '>=', $this->filters['date_start']);
        }
        if (!empty($this->filters['date_end'])) {
            $query->whereDate('date', '<=', $this->filters['date_end']);
        }

        // Exclure le client "Clients divers" si demandé
        if (Auth::check() && Auth::user()->role_id == 2) {
            $query->whereHas('client', function ($q) {
                $q->where('nom_client', '!=', 'Clients divers');
            });
        }

        $ventes = $query->orderBy('date', 'desc')->get();

        return $ventes->transform(function ($v) {
            $data = [
                'Date' => $v->date,
                'Heure' => $v->heure,
                'Client' => $v->client->nom_client ?? null,
                'Destination' => $v->destination,
                'Produit' => $v->produit->articleDepot->nom_article ?? 'N/A',
                'Quantité' => $v->quantite,
                'Unité' => $v->produit->articleDepot->uniteLivraison->nom_unite ?? 'N/A',
                'Lieu de stockage' => $v->produit->lieuStockage->nom ?? 'N/A',
                'Bon de livraison' => $v->bl_id ?? 'N/A',
                'Observation' => $v->observation ?? 'N/A',
                'Véhicule' => $v->vehicule->nom_materiel ?? 'N/A',
                'Chauffeur' => $v->chauffeur->nom_conducteur ?? 'N/A',
            ];

            if (!$this->isAdmin) {
                $data['Observation'] = null; // Masquer pour non-admins
            }

            return $data;
        });
    }

    public function headings(): array
    {
        return [
            'Date',
            'Heure',
            'Client',
            'Destination',
            'Produit',
            'Quantité',
            'Unité',
            'Lieu de stockage',
            'Bon de livraison',
            'Observation',
            'Véhicule',
            'Chauffeur',
        ];
    }
}
