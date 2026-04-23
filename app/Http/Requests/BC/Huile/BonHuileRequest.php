<?php

namespace App\Http\Requests\BC\Huile;

use Illuminate\Foundation\Http\FormRequest;

class BonHuileRequest extends FormRequest
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
            '*.subdivision_id' => 'required|exists:subdivisions,id',
            '*.quantite' => 'required|integer|min:0',
            '*.article_versement_id' => 'nullable|exists:article_depots,id',
            '*.is_consumed' => 'nullable|bool',
        ];
    }

    public function messages(): array
    {
        return [
            '*.num_bon.required' => 'Le numéro de bon est obligatoire.',
            '*.materiel_id.required' => 'Le matériel est obligatoire.',
            '*.materiel_id.exists' => 'Le matériel sélectionné est invalide.',
            '*.subdivision_id.required' => 'La subdivision est obligatoire.',
            '*.subdivision_id.exists' => 'La subdivision sélectionnée est invalide.',
            '*.article_versement_id.exists' => 'L\'article de versement sélectionné est invalide.',
        ];
    }
}
