<?php

namespace App\Exports;

use App\Models\AjustementStock\Stock;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = Stock::with(['lieuStockage', 'articleDepot', 'articleDepot.categorie'])
            ->where(function ($q) {
                $q->whereNull('isAtelierMeca')->orWhere('isAtelierMeca', false);
            });

        if ($this->request->has('search') && !empty($this->request->search)) {
            $search = $this->request->search;
            $query->whereHas('articleDepot', function ($q) use ($search) {
                $q->where('nom_article', 'like', '%' . $search . '%');
            });
        }

        // On peut filtrer par catégorie si nécessaire
        if ($this->request->has('categorie_id') && !empty($this->request->categorie_id)) {
            $query->whereHas('articleDepot', function ($q) {
                $q->where('categorie_id', $this->request->categorie_id);
            });
        }

        $stocks = $query->get();

        // Regroupement par article
        $groupedStocks = $stocks->groupBy('article_id')->map(function ($articleStocks, $articleId) {
            $firstStock = $articleStocks->first();
            $quantiteTotale = $articleStocks->sum('quantite');

            // Préparer détails par lieu
            $lieux = $articleStocks->map(function ($stock) {
                $nomLieu = $stock->lieuStockage->nom ?? 'Sans lieu';
                return $nomLieu . ': ' . $stock->quantite;
            })->implode(', ');

            return [
                'article_id' => $articleId,
                'nom_article' => $firstStock->articleDepot->nom_article,
                'categorie' => $firstStock->articleDepot->categorie->nom_categorie ?? 'N/A',
                'quantite_totale' => $quantiteTotale,
                'lieux' => $lieux,
                'derniere_maj' => $articleStocks->max('updated_at'),
            ];
        });

        return $groupedStocks->values();
    }

    public function headings(): array
    {
        return [
            'ID Article',
            'Nom Article',
            'Catégorie',
            'Quantité Totale',
            'Détails par Lieu',
            'Dernière mise à jour',
        ];
    }

    public function map($stock): array
    {
        return [
            $stock['article_id'],
            $stock['nom_article'],
            $stock['categorie'],
            $stock['quantite_totale'],
            $stock['lieux'],
            $stock['derniere_maj'],
        ];
    }
}
