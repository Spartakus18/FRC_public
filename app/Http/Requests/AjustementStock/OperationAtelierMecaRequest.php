<?php

namespace App\Http\Requests\AjustementStock;

use Illuminate\Foundation\Http\FormRequest;

class OperationAtelierMecaRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'materiel_id' => 'required|exists:materiels,id',
            'gasoil_retirer' => 'required|numeric|min:0.01',
            'gasoil_apres_retrait_cm' => 'nullable|numeric|min:0',
            'commentaire' => 'nullable|string|max:500',
            'operation_at' => 'nullable|date',
        ];
    }

    public function messages()
    {
        return [
            'materiel_id.required' => 'Le matériel est obligatoire.',
            'materiel_id.exists' => 'Le matériel sélectionné est introuvable.',
            'gasoil_retirer.required' => 'La quantité de gasoil à retirer est obligatoire.',
            'gasoil_retirer.numeric' => 'La quantité de gasoil retirée doit être un nombre.',
            'gasoil_retirer.min' => 'La quantité de gasoil retirée doit être supérieure à 0.',
            'gasoil_apres_retrait_cm.numeric' => 'Le gasoil après retrait doit être un nombre.',
            'gasoil_apres_retrait_cm.min' => 'Le gasoil après retrait doit être supérieur ou égal à 0.',
            'commentaire.max' => 'Le commentaire ne doit pas dépasser 500 caractères.',
            'operation_at.date' => 'La date d\'opération est invalide.',
        ];
    }
}
