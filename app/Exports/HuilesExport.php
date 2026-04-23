<?php

namespace App\Exports;

use App\Models\Consommable\Huile;
use App\Models\Parametre\Materiel;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class HuilesExport implements FromCollection, WithHeadings, WithMapping
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Huile::with([
            'materielCible',
            'subdivisionCible',
            'materielSource',
            'subdivisionSource',
            'articleDepot',
            'sourceLieuStockage',
            'bon'
        ])->select('*'); // assure que tous les champs du modèle sont récupérés

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->whereHas('materielCible', function ($q) use ($search) {
                $q->where('nom_materiel', 'like', '%' . $search . '%');
            });
        }

        if (!empty($this->filters['date_start'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_start']);
        }
        if (!empty($this->filters['date_end'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_end']);
        }

        $query->orderBy('created_at', 'desc');

        $huiles = $query->get();

        $user = auth()->user();
        $isAdmin = $user && $user->role_id === 1;

        // Masquer les champs sensibles si non-admin
        if (!$isAdmin) {
            $huiles->transform(function ($huile) {
                $huile->setAttribute('prix_total', null);
                $huile->setAttribute('quantite', null);
                return $huile;
            });
        }

        return $huiles;
    }

    public function headings(): array
    {
        return [
            'N° bon',
            'Soure lieu de stockage',
            'Source station',
            'Quantité',
            'Prix total',
            'Matériel cible',
            'Subdivision cible',
            'Article',
            'Type d\'operation',
            'Materiel source',
            'Subdivision source',
            'Ajouter par',
            'Date',
        ];
    }

    public function map($huile): array
    {
        return [
            $huile->bon->num_bon,
            $huile->sourceLieuStockage->nom ?? '-',
            $huile->source_station ?? '-',
            $huile->quantite,
            $huile->prix_total,
            $huile->materielCible->nom_materiel ?? '-',
            $huile->subdivisionCible->nom_subdivision ?? '-',
            $huile->articleDepot->nom_article ?? '-',
            $huile->type_operation,
            $huile->materielSource->nom_materiel ?? '-',
            $huile->subdivisionSource->nom_subdivision ?? '-',
            $huile->ajouter_par,
            $huile->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
