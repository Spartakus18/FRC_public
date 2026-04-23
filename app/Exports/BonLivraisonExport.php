<?php

namespace App\Exports;

use App\Models\BL\BonLivraison;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BonLivraisonExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = BonLivraison::with([
            'chauffeur',
            'vehicule',
            'aideChauffeur',
            'bonCommandeProduit',
            'bonCommandeProduit.article',
            'bonCommandeProduit.unite',
            'bonCommandeProduit.lieuStockage',
            'bonCommandeProduit.bonCommande',
            'bonCommandeProduit.bonCommande.client',
        ]);

        // Filtres (search, date, client, chauffeur, véhicule, BC)
        if ($this->request->has('search') && !empty($this->request->search)) {
            $search = $this->request->search;
            $query->where(function($q) use ($search) {
                $q->where('numBL', 'like', '%' . $search . '%')
                  ->orWhereHas('bonCommande.client', function($q2) use ($search) {
                      $q2->where('nom_client', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('chauffeur', function($q3) use ($search) {
                      $q3->where('nom_conducteur', 'like', '%' . $search . '%');
                  });
            });
        }

        if ($this->request->has('date_start') && !empty($this->request->date_start)) {
            $query->whereDate('date_livraison', '>=', $this->request->date_start);
        }

        if ($this->request->has('date_end') && !empty($this->request->date_end)) {
            $query->whereDate('date_livraison', '<=', $this->request->date_end);
        }

        if ($this->request->has('client_id') && !empty($this->request->client_id)) {
            $query->where('client_id', $this->request->client_id);
        }

        if ($this->request->has('chauffeur_id') && !empty($this->request->chauffeur_id)) {
            $query->where('chauffeur_id', $this->request->chauffeur_id);
        }

        if ($this->request->has('vehicule_id') && !empty($this->request->vehicule_id)) {
            $query->where('vehicule_id', $this->request->vehicule_id);
        }

        if ($this->request->has('bc_id') && !empty($this->request->bc_id)) {
            $query->where('BC_id', $this->request->bc_id);
        }

        // Exclure le client "Clients divers" si demandé
        if (Auth::check() && Auth::user()->role_id == 2) {
            $query->whereHas('client', function ($q) {
                $q->where('nom_client', '!=', 'Clients divers');
            });
        }

        return $query->orderBy('date_livraison', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'N° BL',
            'Client',
            'Bon de commande',
            'Article',
            'Quantité BC',
            'Quantité déjà livré',
            'Quantité reste à livré',
            'Unité',
            'Lieu livraison',
            'Chauffeur',
            'Aide Chauffeur',
            'Véhicule',
            'Date livraison',
            'Prix unitaire (Ar)',
            'Heure départ',
            'Heure arrivée',
            'Gasoil départ (Cm)',
            'Gasoil arrivée (Cm)',
            'Compteur départ',
            'Compteur arrivée',
            'Nombre voyages',
            'Heure machine',
            'Heure chauffeur',
            'Date arrivée',
            'Consommation réelle par heure',
            'Consommation horaire reférence',
            'Ecart Consommation horaire',
            'Statut consommation horaire',
            'Consommation totale',
            'Consommation destination réference',
            'ecart consommation destination',
            'Status consommation destination',
            'Statut',
            'Remarque',
        ];
    }

    public function map($bl): array
    {
        $reste = $bl->bonCommandeProduit->quantite - $bl->quantite_deja_livree;

        return [
            $bl->numBL,
            $bl->bonCommandeProduit->bonCommande->client->nom_client ?? 'N/A',
            $bl->bonCommandeProduit->bonCommande->numero ?? 'N/A',
            $bl->bonCommandeProduit->article->nom_article ?? 'N/A',
            $bl->bonCommandeProduit->quantite ?? 0,
            $bl->quantite_deja_livree,
            $reste ?? 0,
            $bl->bonCommandeProduit->unite->nom_unite ?? 'N/A',
            $bl->bonCommandeProduit->lieuStockage->nom ?? 'N/A',
            $bl->chauffeur->nom_conducteur ?? 'N/A',
            $bl->aideChauffeur->nom_aideChauffeur ?? 'N/A',
            $bl->vehicule->nom_materiel ?? 'N/A',
            $bl->date_livraison,
            $bl->PU ?? 0,
            $bl->heure_depart ?? 'N/A',
            $bl->heure_arrive ?? 'N/A',
            $bl->gasoil_depart ?? 0,
            $bl->gasoil_arrive ?? 0,
            $bl->compteur_depart ?? 0,
            $bl->compteur_arrive ?? 0,
            $bl->nbr_voyage ?? 'N/A',
            $bl->heure_machine ?? 0,
            $bl->heure_chauffeur ?? 0,
            $bl->date_arriver ?? 'N/A',
            $bl->consommation_reelle_par_heure ?? 'N/A',
            $bl->consommation_horaire_reference ?? 'N/A',
            $bl->ecart_consommation_horaire ?? 'N/A',
            $bl->statut_consommation_horaire ?? '',
            $bl->consommation_totale ?? 'N/A',
            $bl->consommation_destination_reference ?? 'N/A',
            $bl->ecart_consommation_destination ?? 'N/A',
            $bl->statut_consommation_destination ?? '',
            $bl->isDelivred ? 'Livré' : 'En cours',
            $bl->remarque ?? '',
        ];
    }
}
