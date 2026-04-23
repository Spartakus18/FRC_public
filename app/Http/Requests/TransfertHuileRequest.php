<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransfertHuileRequest extends FormRequest
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
            'materiel_id_source' => 'required|exists:materiels,id',
            'materiel_id_cible' => 'required|exists:materiels,id',
            'subdivision_id_source' => 'nullable|exists:subdivisions,id',
            'subdivision_id_cible' => 'nullable|exists:subdivisions,id',
            'article_versement_id' => 'required|exists:article_depots,id',
        ];
    }

    public function messages()
    {
        return [
            'materiel_id_source.required' => 'Le matériel source est requis',
            'materiel_id_cible.required' => 'Le matériel cible est requis',
            'article_versement_id.required' => "Le type d'huile est requis",
        ];
    }
}
