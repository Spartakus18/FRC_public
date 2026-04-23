<?php

namespace App\Exports;

use App\Models\BC\Bon_commande;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BonCommandeExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function query()
    {
        $query = Bon_commande::with(['client', 'destination', 'produits']);

        // Appliquer les mêmes filtres que dans le contrôleur
        if ($this->request->has('search') && !empty($this->request->search)) {
            $search = $this->request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', '%' . $search . '%')
                  ->orWhere('designation', 'like', '%' . $search . '%')
                  ->orWhereHas('client', function ($q2) use ($search) {
                      $q2->where('nom_client', 'like', '%' . $search . '%');
                  });
            });
        }

        if ($this->request->has('date_start') && !empty($this->request->date_start)) {
            $query->whereDate('date_BC', '>=', $this->request->date_start);
        }

        if ($this->request->has('date_end') && !empty($this->request->date_end)) {
            $query->whereDate('date_BC', '<=', $this->request->date_end);
        }

        if ($this->request->has('client_id') && !empty($this->request->client_id)) {
            $query->where('client_id', $this->request->client_id);
        }

        if ($this->request->has('destination_id') && !empty($this->request->destination_id)) {
            $query->where('destination_id', $this->request->destination_id);
        }

        // Exclure le client "Clients divers" si demandé
        if (Auth::check() && Auth::user()->role_id == 2) {
            $query->whereHas('client', function ($q) {
                $q->where('nom_client', '!=', 'Clients divers');
            });
        }

        $query->orderBy('date_BC', 'desc');

        return $query;
    }

    public function headings(): array
    {
        return [
            'N° Bon',
            'Année', // Nouvelle colonne pour différencier les doublons
            'Date BC',
            'Client',
            'Destination',
            'Désignation',
            'Date élaboration',
            'Date prévue livraison',
            'Nombre de produits',
            'Montant total',
            'Observations',
        ];
    }

    public function map($bon): array
    {
        // Calculer le montant total
        $total = $bon->produits->sum('montant');

        // Extraire l'année de date_BC
        $annee = date('Y', strtotime($bon->date_BC));

        return [
            $bon->numero,
            $annee, // Ajout de l'année dans l'export
            date('d/m/Y', strtotime($bon->date_BC)),
            $bon->client->nom_client ?? '',
            $bon->destination->nom_destination ?? '',
            $bon->designation,
            date('d/m/Y', strtotime($bon->date_elaboration)),
            date('d/m/Y', strtotime($bon->date_prevu_livraison)),
            $bon->produits->count(),
            number_format($total, 2, ',', ' ') . ' Ar',
            $bon->observations,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style pour l'en-tête
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '2E5BFF']
                ]
            ],
        ];
    }
}
