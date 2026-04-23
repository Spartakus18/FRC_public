<?php

namespace App\Http\Requests\Consommable;

use App\Models\AjustementStock\Lieu_stockage;
use Illuminate\Foundation\Http\FormRequest;

class HuileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Autoriser tous les utilisateurs authentifiés
        return auth()->check();
    }

    public function rules(): array
    {
        $id = $this->route('huile')?->id; // utile pour update

        return [
            'source' => 'sometimes|nullable',
            //'source_station' => 'sometimes|nullable|string',
            'source_lieu_stockage_id' => 'sometimes|nullable|exists:lieu_stockages,id',
            'quantite' => 'required|numeric|min:0.1',
            'prix_total' => 'nullable|numeric|min:0',
            //type d'operation
            'type_operation' => 'sometimes|string',

            // Relations obligatoires
            // materiel cible
            'materiel_id_cible' => 'required|exists:materiels,id',
            'subdivision_id_cible' => 'required|exists:subdivisions,id',
            'article_versement_id' => 'required|exists:article_depots,id',

            // matriel source
            'materiel_id_source' => 'nullable|exists:materiels,id',
            'subdivision_id_source' => 'nullable|exists:subdivisions,id',

            // bon huile
            'bon_id' => 'sometimes|exists:bon_huiles,id'
        ];
    }

    public function messages(): array
    {
        return [
            'num_bon.unique' => 'Le numéro du bon doit être unique.',
            'quantite.required' => 'La quantité en litres est obligatoire.',
            'materiel_id_cible.required' => 'Veuillez sélectionner le matériel cible.',
            'subdivision_id_cible.required' => 'Veuillez sélectionner la subdivision cible.',
            'article_id.required' => 'Veuillez sélectionner un type d’huile.',
        ];
    }
}
