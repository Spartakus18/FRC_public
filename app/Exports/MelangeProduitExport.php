<?php

namespace App\Exports;

use App\Models\Produit\MelangeProduit;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class MelangeProduitExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = MelangeProduit::with([
            'produit1',
            'produit2', 
            'produitObtenu',
            'lieuStockage1',
            'lieuStockage2',
            'lieuStockageObtenu'
        ]);

        // Filtres
        if ($this->request->has('search') && !empty($this->request->search)) {
            $search = $this->request->search;
            $query->where(function($q) use ($search) {
                $q->where('observation', 'like', '%' . $search . '%')
                  ->orWhereHas('produit1', function($q2) use ($search) {
                      $q2->where('nom_article', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('produit2', function($q3) use ($search) {
                      $q3->where('nom_article', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('produitObtenu', function($q4) use ($search) {
                      $q4->where('nom_article', 'like', '%' . $search . '%');
                  });
            });
        }

        if ($this->request->has('date_start') && !empty($this->request->date_start)) {
            $query->whereDate('date', '>=', $this->request->date_start);
        }

        if ($this->request->has('date_end') && !empty($this->request->date_end)) {
            $query->whereDate('date', '<=', $this->request->date_end);
        }

        if ($this->request->has('produit_source_id1') && !empty($this->request->produit_source_id1)) {
            $query->where('produit_source_id1', $this->request->produit_source_id1);
        }

        if ($this->request->has('produit_source_id2') && !empty($this->request->produit_source_id2)) {
            $query->where('produit_source_id2', $this->request->produit_source_id2);
        }

        if ($this->request->has('produit_obtenu_id') && !empty($this->request->produit_obtenu_id)) {
            $query->where('produit_obtenu_id', $this->request->produit_obtenu_id);
        }

        return $query->orderBy('date', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Date',
            'Produit Source 1',
            'Produit Source 2', 
            'Produit Obtenu',
            'Lieu Stockage Source 1',
            'Lieu Stockage Source 2',
            'Lieu Stockage Obtenu',
            'Quantité Source 1',
            'Quantité Source 2',
            'Total Obtenu',
            'Observation'
        ];
    }

    public function map($melange): array
    {
        return [
            $melange->date,
            $melange->produit1->nom_article ?? 'N/A',
            $melange->produit2->nom_article ?? 'N/A',
            $melange->produitObtenu->nom_article ?? 'N/A',
            $melange->lieuStockage1->nom ?? 'N/A',
            $melange->lieuStockage2->nom ?? 'N/A',
            $melange->lieuStockageObtenu->nom ?? 'N/A',
            $melange->quantite_source1,
            $melange->quantite_source2,
            $melange->total,
            $melange->observation,
        ];
    }
}