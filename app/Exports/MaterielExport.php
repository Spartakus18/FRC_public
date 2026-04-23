<?php

namespace App\Exports;

use App\Models\Parametre\Materiel;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MaterielExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = Materiel::query();

        if ($this->request->has('search') && !empty($this->request->search)) {
            $search = $this->request->search;
            $query->where('nom_materiel', 'like', '%' . $search . '%');
        }

        if ($this->request->has('categorie') && !empty($this->request->categorie)) {
            $query->where('categorie', $this->request->categorie);
        }

        if ($this->request->has('status') && $this->request->status !== '') {
            $query->where('status', $this->request->status);
        }

        return $query->orderBy('nom_materiel', 'asc')->get();
    }

    public function headings(): array
    {
        return [
            'Nom du matériel',
            'Statut',
            'Catégorie',
            'Capacité (L)',
            'Capacité (Cm)',
            'Seuil de sécurité',
            'Consommation horaire',
            'Gasoil actuel',
            'Consommation gasoil',
            'Nombre pneu',
            'Compteur actuel',
        ];
    }

    public function map($materiel): array
    {
        return [
            $materiel->nom_materiel,
            $materiel->status ? 'Disponible' : 'Indisponible',
            $materiel->categorie,
            $materiel->capaciteL,
            $materiel->capaciteCm,
            $materiel->seuil,
            $materiel->consommation_horaire,
            $materiel->actuelGasoil,
            $materiel->gasoil_consommation,
            $materiel->nbr_pneu,
            $materiel->compteur_actuel,
        ];
    }
}
