<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

class GasoilExport implements FromQuery, WithMapping, WithHeadings
{
    protected $search;
    protected $dateStart;
    protected $dateEnd;

    public function __construct($filters = [])
    {
        $this->search = $filters['search'] ?? null;
        $this->dateStart = $filters['date_start'] ?? null;
        $this->dateEnd = $filters['date_end'] ?? null;
    }

    public function query()
    {
        $query = \App\Models\Consommable\Gasoil::query()->with(['materielCible', 'source']);

        if ($this->search) {
            $query->whereHas('materielCible', function ($q) {
                $q->where('nom_materiel', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->dateStart) {
            $query->whereDate('created_at', '>=', $this->dateStart);
        }

        if ($this->dateEnd) {
            $query->whereDate('created_at', '<=', $this->dateEnd);
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function map($gasoil): array
    {
        return [
            $gasoil->id,
            $gasoil->materielCible->nom_materiel ?? 'N/A',
            $gasoil->quantite,
            $gasoil->type_operation,
            $gasoil->source->nom ?? $gasoil->source_station ?? '-',
            $gasoil->prix_gasoil ?? '-',
            $gasoil->prix_total ?? '-',
            $gasoil->ajouter_par ?? '-',
            $gasoil->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Matériel',
            'Quantité',
            'Type d\'operation',
            'Source',
            'Prix Gasoil',
            'Prix Total',
            'Ajouté par',
            'Date Création',
        ];
    }
}
