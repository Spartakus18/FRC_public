<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePerteGasoilRequest extends FormRequest
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
            'modifications' => 'required|array|min:1',
            'modifications.*.id' => 'required|integer|exists:materiels,id',
            'modifications.*.gasoilHier' => ['required', 'numeric', 'min:0'],
            'modifications.*.gasoilMaj' => ['required', 'numeric'],
            'modifications.*.motif' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Messages de validation personnalisés
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'modifications.required' => 'Les modifications sont requises.',
            'modifications.array' => 'Les modifications doivent être un tableau.',
            'modifications.min' => 'Au moins une modification est requise.',

            'modifications.*.id.required' => 'L\'identifiant du matériel est requis.',
            'modifications.*.id.integer' => 'L\'identifiant du matériel doit être un entier.',
            'modifications.*.id.exists' => 'Le matériel spécifié n\'existe pas.',

            'modifications.*.gasoilHier.required' => 'La quantité de gasoil d\'hier est requise.',
            'modifications.*.gasoilHier.numeric' => 'La quantité de gasoil d\'hier doit être un nombre.',
            'modifications.*.gasoilHier.min' => 'La quantité de gasoil d\'hier ne peut pas être négative.',

            'modifications.*.gasoilMaj.required' => 'La quantité de gasoil mise à jour est requise.',
            'modifications.*.gasoilMaj.numeric' => 'La quantité de gasoil mise à jour doit être un nombre.',

            'modifications.*.motif.required' => 'Le motif de la modification est requis.',
            'modifications.*.motif.string' => 'Le motif doit être une chaîne de caractères.',
            'modifications.*.motif.max' => 'Le motif ne peut pas dépasser 500 caractères.',
        ];
    }
}
