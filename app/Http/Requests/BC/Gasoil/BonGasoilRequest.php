<?php

namespace App\Http\Requests\BC\Gasoil;

use Illuminate\Foundation\Http\FormRequest;

class BonGasoilRequest extends FormRequest
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
            '*.num_bon' => [
                'required',
                'string',
                'max:50',
            ],
            '*.materiel_id' => 'required|exists:materiels,id',
            '*.quantite' => 'required|integer|min:1',
            '*.is_consumed' => 'nullable|bool',
            '*.ajouter_par' => 'nullable|string|max:100',
            '*.modifier_par' => 'nullable|string|max:100'
        ];
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            '*.num_bon.required' => 'Le numéro de bon est obligatoire.',
            '*.materiel_id.required' => 'Le matériel est obligatoire.',
            '*.materiel_id.exists' => 'Le matériel sélectionné est invalide.',
            '*.quantite.required' => 'La quantité est obligatoire.',
            '*.quantite.min' => 'La quantité doit être au moins 1.',
        ];
    }
}
