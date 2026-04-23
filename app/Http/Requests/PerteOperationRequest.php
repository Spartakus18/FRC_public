<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PerteOperationRequest extends FormRequest
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
            'modificationManuelle' => 'nullable|boolean',
            'actuelGasoil' => 'nullable|required_if:modificationManuelle,true|numeric|min:0',
            'gasoilApresAjout' => 'nullable|required_if:modificationManuelle,true|numeric|min:0',
            'motifModification' => 'nullable|string|max:500'
        ];
    }
}
