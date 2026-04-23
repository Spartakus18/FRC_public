<?php

namespace App\Exports;

use App\Models\Parametre\Pneu;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Auth;

class PneusExport implements FromCollection, WithHeadings
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Pneu::with('materiel');

        // Appliquer les filtres
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('num_serie', 'like', '%' . $search . '%')
                  ->orWhere('caracteristiques', 'like', '%' . $search . '%')
                  ->orWhere('marque', 'like', '%' . $search . '%')
                  ->orWhere('type', 'like', '%' . $search . '%')
                  ->orWhereHas('materiel', fn($q) => $q->where('nom_materiel', 'like', '%' . $search . '%'));
            });
        }

        if (!empty($this->filters['date_start'])) {
            $query->whereDate('date_obtention', '>=', $this->filters['date_start']);
        }

        if (!empty($this->filters['date_end'])) {
            $query->whereDate('date_obtention', '<=', $this->filters['date_end']);
        }

        if (!empty($this->filters['etat'])) {
            $query->where('etat', $this->filters['etat']);
        }

        if (!empty($this->filters['situation'])) {
            $query->where('situation', $this->filters['situation']);
        }

        $query->orderBy('created_at', 'desc');

        $pneus = $query->get();

        // Vérifier si l'utilisateur est admin
        $user = Auth::user();
        $isAdmin = $user && $user->role_id === 1;

        // Transformer les données pour Excel
        $pneus->transform(function ($pneu) use ($isAdmin) {
            $data = [
                'ID' => $pneu->id,
                'Numéro de série' => $pneu->num_serie,
                'Marque' => $pneu->marque,
                'Type' => $pneu->type,
                'Caractéristiques' => $pneu->caracteristiques,
                'État' => $pneu->etat,
                'Situation' => $pneu->situation,
                'Date obtention' => $pneu->date_obtention,
                'Date mise en service' => $pneu->date_mise_en_service,
                'Date mise hors service' => $pneu->date_mise_hors_service,
                'Emplacement' => $pneu->emplacement,
                'Observations' => $pneu->observations,
                'Kilométrage' => $pneu->kilometrage,
                'Matériel' => $pneu->materiel->nom_materiel ?? null,
                'Créé le' => $pneu->created_at,
                'Modifié le' => $pneu->updated_at,
            ];

            if (!$isAdmin) {
                // Masquer certains champs sensibles pour les non-admins
                $data['Observations'] = null;
                $data['Kilométrage'] = null;
            }

            return new Collection($data);
        });

        return $pneus;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Numéro de série',
            'Marque',
            'Type',
            'Caractéristiques',
            'État',
            'Situation',
            'Date obtention',
            'Date mise en service',
            'Date mise hors service',
            'Emplacement',
            'Observations',
            'Kilométrage',
            'Matériel',
            'Créé le',
            'Modifié le'
        ];
    }
}
