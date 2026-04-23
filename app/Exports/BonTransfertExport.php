<?php

namespace App\Exports;

use App\Models\Produit\BonTransfert;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class BonTransfertExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = BonTransfert::with([
            'produit',
            'lieuStockageDepart',
            'lieuStockageArrive',
            'user'
        ]);

        // Filtres
        if ($this->request->has('search') && !empty($this->request->search)) {
            $search = $this->request->search;
            $query->where(function($q) use ($search) {
                $q->where('numero_bon', 'like', '%' . $search . '%')
                  ->orWhereHas('produit', function($q2) use ($search) {
                      $q2->where('nom_article', 'like', '%' . $search . '%');
                  });
            });
        }

        if ($this->request->has('est_utilise') && $this->request->est_utilise !== '') {
            $query->where('est_utilise', $this->request->est_utilise);
        }

        if ($this->request->has('date_start') && !empty($this->request->date_start)) {
            $query->whereDate('created_at', '>=', $this->request->date_start);
        }

        if ($this->request->has('date_end') && !empty($this->request->date_end)) {
            $query->whereDate('created_at', '<=', $this->request->date_end);
        }

        if ($this->request->has('produit_id') && !empty($this->request->produit_id)) {
            $query->where('produit_id', $this->request->produit_id);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'N° Bon',
            'Date Transfert',
            'Produit',
            'Quantité',
            'Lieu Départ',
            'Lieu Arrivée',
            'Créé par',
            'Statut',
            'Commentaire',
            'Date création'
        ];
    }

    public function map($bon): array
    {
        return [
            $bon->numero_bon,
            $bon->date_transfert,
            $bon->produit->nom_article ?? 'N/A',
            $bon->quantite,
            $bon->lieuStockageDepart->nom ?? 'N/A',
            $bon->lieuStockageArrive->nom ?? 'N/A',
            $bon->user->nom ?? 'N/A',
            $bon->est_utilise ? 'Utilisé' : 'Disponible',
            $bon->commentaire,
            $bon->created_at->format('Y-m-d H:i:s'),
        ];
    }
}