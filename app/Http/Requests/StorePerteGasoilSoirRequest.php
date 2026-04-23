<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePerteGasoilSoirRequest extends FormRequest
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
            'pertes' => 'required|array',
            'pertes.*.materiel_id' => 'required|exists:materiels,id',
            'pertes.*.quantite_precedente_soir' => 'required|numeric|min:0',
            'pertes.*.quantite_actuelle_soir' => 'required|numeric|min:0',
            'pertes.*.quantite_perdue_soir' => 'required|numeric|min:0',
            'pertes.*.raison_perte_soir' => 'nullable|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'pertes.required' => 'Le tableau des pertes est requis.',
            'pertes.*.materiel_id.required' => 'L\'ID du matériel est requis.',
            'pertes.*.materiel_id.exists' => 'Le matériel sélectionné n\'existe pas.',
        ];
    }

}
