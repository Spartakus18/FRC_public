<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VersementHuileAchatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'type_bon' => 'required|string|in:approStock,achat,transfert',
            'num_bon' => 'required|string|max:255',
            'quantite' => 'required|numeric|min:0',
            'prix_total' => 'nullable|numeric|min:0',
            'materiel_id_cible' => 'required|exists:materiels,id',
            'subdivision_id_cible' => 'nullable|exists:subdivisions,id',
            'article_versement_id' => 'required|exists:article_depots,id',
            'source' => 'required|in:station,lieu_stockage',
            'source_lieu_stockage_id' => 'nullable|required_if:source,lieu_stockage|exists:lieu_stockages,id',
        ];
    }

    public function messages()
    {
        return [
            'type_bon.required' => 'Le type de bon est requis',
            'num_bon.required' => 'Le numéro de bon est requis',
            'quantite.required' => 'La quantité est requise',
            'materiel_id_cible.required' => 'Le matériel cible est requis',
            'article_versement_id.required' => "Le type d'huile est requis",
            'source.required' => 'La source est requise',
            'source_lieu_stockage_id.required_if' => 'Le lieu de stockage est requis lorsque la source est un lieu de stockage',
        ];
    }
}
