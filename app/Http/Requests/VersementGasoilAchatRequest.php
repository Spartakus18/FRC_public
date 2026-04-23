<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VersementGasoilAchatRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'type_bon' => 'required|string|in:approStock,achat,transfert',
            'num_bon' => 'required|string|max:255',
            'quantite' => 'required|numeric|min:0',
            'prix_gasoil' => 'nullable|numeric|min:0',
            'materiel_id_cible' => 'required|exists:materiels,id',
            'source' => 'required|in:station,lieu_stockage',
            'source_lieu_stockage_id' => 'nullable|required_if:source,lieu_stockage|exists:lieu_stockages,id',

            // Champs pour modification manuelle
            'modificationManuelle' => 'nullable|boolean',
            'actuelGasoil' => 'nullable|required_if:modificationManuelle,true|numeric|min:0',
            'gasoilApresAjout' => 'nullable|required_if:modificationManuelle,true|numeric|min:0',
            'motifModification' => 'nullable|string|max:500'
        ];
    }

    public function messages()
    {
        return [
            'num_bon.required' => 'Le numéro de bon est requis',
            'quantite.required' => 'La quantité est requise',
            'materiel_id_cible.required' => 'Le matériel cible est requis',
            'source.required' => 'La source est requise',
            'source_lieu_stockage_id.required_if' => 'Le lieu de stockage est requis lorsque la source est un lieu de stockage',
            'actuelGasoil.required_if' => 'Le gasoil actuel est requis pour une modification manuelle',
            'gasoilApresAjout.required_if' => 'Le gasoil après ajout est requis pour une modification manuelle'
        ];
    }
}
