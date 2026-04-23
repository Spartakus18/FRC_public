<?php

namespace App\Exports;

use App\Models\Fourniture\Fourniture;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class FournitureExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    /**
     * Constructeur : reçoit la requête HTTP avec les filtres
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Récupère les données filtrées (sans pagination)
     */
    public function collection()
    {
        $query = Fourniture::with('materiel');

        // Recherche par texte (nom, référence, n° série, matériel)
        if ($this->request->has('search') && !empty($this->request->search)) {
            $search = $this->request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom_article', 'like', '%' . $search . '%')
                    ->orWhere('reference', 'like', '%' . $search . '%')
                    ->orWhere('numero_serie', 'like', '%' . $search . '%')
                    ->orWhereHas('materiel', function ($q) use ($search) {
                        $q->where('nom_materiel', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filtre par état
        if ($this->request->has('etat') && !empty($this->request->etat)) {
            $query->where('etat', $this->request->etat);
        }

        // Filtre par disponibilité
        if ($this->request->has('is_dispo') && $this->request->is_dispo !== null && $this->request->is_dispo !== '') {
            $query->where('is_dispo', filter_var($this->request->is_dispo, FILTER_VALIDATE_BOOLEAN));
        }

        // Filtre par localisation
        if ($this->request->has('localisation') && !empty($this->request->localisation)) {
            $query->where('localisation_actuelle', $this->request->localisation);
        }

        // Filtres par dates (date_acquisition)
        if ($this->request->has('date_start') && !empty($this->request->date_start)) {
            $query->whereDate('date_acquisition', '>=', $this->request->date_start);
        }
        if ($this->request->has('date_end') && !empty($this->request->date_end)) {
            $query->whereDate('date_acquisition', '<=', $this->request->date_end);
        }

        // Tri par défaut
        $query->orderBy('created_at', 'desc');

        return $query->get();
    }

    /**
     * En‑têtes des colonnes Excel
     */
    public function headings(): array
    {
        return [
            'ID',
            'Nom article',
            'Référence',
            'Numéro série',
            'État',
            'Disponible',
            'Date acquisition',
            'Matériel associé',
            'Localisation',
            'Date sortie stock',
            'Date retour stock',
            'Commentaire',
            'Créé le',
        ];
    }

    /**
     * Formatage de chaque ligne
     */
    public function map($fourniture): array
    {
        return [
            $fourniture->id,
            $fourniture->nom_article,
            $fourniture->reference,
            $fourniture->numero_serie,
            $this->formatEtat($fourniture->etat),
            $fourniture->is_dispo ? 'Oui' : 'Non',
            $fourniture->date_acquisition,
            $fourniture->materiel->nom_materiel ?? '-',
            $fourniture->localisation_actuelle ?? '-',
            $fourniture->date_sortie_stock,
            $fourniture->date_retour_stock,
            $fourniture->commentaire ?? '-',
            $fourniture->created_at->format('d/m/Y H:i'),
        ];
    }

    /**
     * Traduit le code état en libellé lisible
     */
    private function formatEtat($etat): string
    {
        $etats = [
            'neuf'         => 'Neuf',
            'bon'          => 'Bon état',
            'moyen'        => 'État moyen',
            'a_verifier'   => 'À vérifier',
            'hors_service' => 'Hors service',
        ];

        return $etats[$etat] ?? $etat;
    }
}
