<?php

namespace App\Exports;

use App\Models\Produit\Groupe;
use Maatwebsite\Excel\Concerns\FromCollection;

class GroupeExport implements FromCollection
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = Groupe::query();

        if ($this->request->has('search') && !empty($this->request->search)) {
            $search = $this->request->search;
            $query->where('nom_groupe', 'like', '%' . $search . '%');
        }

        return $query->get(['id', 'nom_groupe', 'status', 'capaciteL', 'seuil', 'actuelGasoil', 'created_at']);
    }

    public function headings() : array {
        return [
            'ID',
            'Nom du Groupe',
            'Capacité (L)',
            'Seuil Gazoil',
            'Gazoil Actuel',
            'Date de création'
        ];
    }
}
