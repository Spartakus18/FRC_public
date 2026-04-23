<?php

namespace App\Http\Requests\Consommable;

use App\Models\AjustementStock\Lieu_stockage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GasoilRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // return auth()->user() && auth()->user()->role === 'admin';
        return true;
    }

    /**
     * Règles de validation
     */
    public function rules(): array
    {
        return [
            // Règle personnalisée pour 'source'
            'source' => [
                'required',
                function ($attribute, $value, $fail) {
                    $value = is_string($value) ? trim($value) : $value;

                    if ($value === 'station') {
                        return;
                    }

                    if (is_numeric($value) && (int)$value > 0) {
                        if (Lieu_stockage::where('id', (int)$value)->exists()) {
                            return;
                        }
                    }

                    $fail('Le champ source doit être soit "station" soit l\'id d\'un lieu de stockage existant.');
                },
            ],
            'source_station' => 'sometimes|nullable|string',
            'source_lieu_stockage_id' => 'sometimes|nullable|exists:lieu_stockages,id',

            'quantite' => 'nullable|numeric|min:0',

            'prix_gasoil' => 'nullable|numeric|min:0',

            // prix_total ne doit pas venir du front → calculé automatiquement
            'prix_total' => 'prohibited',

            // Matériel cible est obligatoire
            'materiel_id_cible' => 'nullable|exists:materiels,id',
            'groupe_id_cible' => 'nullable|exists:groupes,id',

            'ajouter_par'  => 'nullable|string|max:100', // sera rempli par backend
            'modifier_par' => 'nullable|string|max:100',
            'bon_id' => 'sometimes|exists:bon_gasoils,id'
        ];
    }

    /**
     * Messages d’erreurs personnalisés
     */
    public function messages(): array
    {
        return [
            'vehicule_id_cible.required' => 'Le véhicule cible est obligatoire.',
            'vehicule_id_select.different' => 'Le véhicule source et cible doivent être différents pour un transfert.',
            'prix_total.prohibited' => 'Le prix total est calculé automatiquement, inutile de l’envoyer.',
        ];
    }
}
