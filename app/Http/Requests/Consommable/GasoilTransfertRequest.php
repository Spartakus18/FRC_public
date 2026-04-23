<?php

namespace App\Http\Requests\Consommable;

use Illuminate\Foundation\Http\FormRequest;

class GasoilTransfertRequest extends FormRequest
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
            'source' => 'nullable',
            'quantite' => 'required|numeric|min:0',
            'materiel_id_cible' => 'required|exists:materiels,id',
            'materiel_id_source' => 'required|exists:materiels,id',
            'prix_gasoil' => 'nullable|numeric|min:0',
            'ajouter_par' => 'nullable|string|max:100',
            // Ajout des règles pour la modification manuelle
            'modificationManuelle' => 'nullable|boolean',
            'actuelGasoil' => 'nullable|numeric|min:0',
            'gasoilApresAjout' => 'nullable|numeric|min:0',
            'motifModification' => 'nullable|string|max:500',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Si modificationManuelle est true, vérifier que le motif est fourni
            $modificationManuelle = $this->input('modificationManuelle');
            $isModificationManuelle = $modificationManuelle === true || $modificationManuelle === 'true' || $modificationManuelle === 1 || $modificationManuelle === '1';

            if ($isModificationManuelle) {
                if (!$this->filled('motifModification')) {
                    $validator->errors()->add('motifModification', 'Le motif de modification est requis lorsque la modification est manuelle.');
                }

                // Vérifier que actuelGasoil et gasoilApresAjout sont fournis
                if (!$this->filled('actuelGasoil') || !$this->filled('gasoilApresAjout')) {
                    $validator->errors()->add('modificationManuelle', 'Les valeurs modifiées (actuelGasoil et gasoilApresAjout) sont requises pour une modification manuelle.');
                }
            }

            // Si materiel_id_source et materiel_id_cible sont identiques
            if ($this->input('materiel_id_source') === $this->input('materiel_id_cible')) {
                $validator->errors()->add('materiel_id_cible', 'Le matériel source et le matériel cible doivent être différents.');
            }
        });
    }
}
