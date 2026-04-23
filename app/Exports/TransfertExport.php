<?php

namespace App\Exports;

use App\Models\Produit\TransfertProduit;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class TransfertExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = TransfertProduit::with([
            'materiel',
            'chauffeur',
            'aideChauffeur',
            'lieuStockageDepart',
            'lieuStockageArrive',
            'produit',
            'bonTransfert'
        ]);

        // Filtres
        if ($this->request->has('search') && !empty($this->request->search)) {
            $search = $this->request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('produit', function ($q2) use ($search) {
                    $q2->where('nom_article', 'like', '%' . $search . '%');
                })
                    ->orWhereHas('chauffeur', function ($q3) use ($search) {
                        $q3->where('nom_conducteur', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('materiel', function ($q4) use ($search) {
                        $q4->where('nom_materiel', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('bonTransfert', function ($q5) use ($search) {
                        $q5->where('numero_bon', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($this->request->has('date_start') && !empty($this->request->date_start)) {
            $query->whereDate('date', '>=', $this->request->date_start);
        }

        if ($this->request->has('date_end') && !empty($this->request->date_end)) {
            $query->whereDate('date', '<=', $this->request->date_end);
        }

        if ($this->request->has('produit_id') && !empty($this->request->produit_id)) {
            $query->where('produit_id', $this->request->produit_id);
        }

        if ($this->request->has('lieu_depart_id') && !empty($this->request->lieu_depart_id)) {
            $query->where('lieu_stockage_depart_id', $this->request->lieu_depart_id);
        }

        if ($this->request->has('lieu_arrive_id') && !empty($this->request->lieu_arrive_id)) {
            $query->where('lieu_stockage_arrive_id', $this->request->lieu_arrive_id);
        }

        if ($this->request->has('isDelivred') && $this->request->isDelivred !== '') {
            $query->where('isDelivred', $this->request->isDelivred);
        }

        return $query->orderBy('date', 'desc')->orderBy('heure_depart', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Date',
            'Heure Départ',
            'Heure Arrivée',
            'Produit',
            'Quantité',
            'Lieu Départ',
            'Lieu Arrivée',
            'Véhicule',
            'Chauffeur',
            'Aide Chauffeur',
            'Gasoil Départ',
            'Gasoil Arrivée',
            'Bon Transfert',
            'Statut',
            'Consommation reelle par heure',
            'Consommation horaire reference',
            'Ecart consommation horaire',
            'Statut consommation horaire',
            'Consommation totale',
            'Consommation destination reference',
            'Ecart consommation destination',
            'Statut consommation destination',
            'Remarque'
        ];
    }

    public function map($transfert): array
    {
        return [
            $transfert->date,
            $transfert->heure_depart,
            $transfert->heure_arrivee ?? 'Non arrivé',
            $transfert->produit->nom_article ?? 'N/A',
            $transfert->quantite,
            $transfert->lieuStockageDepart->nom ?? 'N/A',
            $transfert->lieuStockageArrive->nom ?? 'N/A',
            $transfert->materiel->nom_materiel ?? 'N/A',
            $transfert->chauffeur->nom_conducteur ?? 'N/A',
            $transfert->aideChauffeur->nom_aide ?? 'N/A',
            $transfert->gasoil_depart,
            $transfert->gasoil_arrive ?? 'N/A',
            $transfert->bonTransfert->numero_bon ?? 'N/A',
            $transfert->isDelivred ? 'Livré' : 'En cours',
            $transfert->consommation_reelle_par_heure,
            $transfert->consommation_horaire_reference,
            $transfert->ecart_consommation_horaire,
            $transfert->statut_consommation_horaire,
            $transfert->consommation_totale,
            $transfert->consommation_destination_reference,
            $transfert->ecart_consommation_destination,
            $transfert->statut_consommation_destination,
            $transfert->remarque,
        ];
    }
}
