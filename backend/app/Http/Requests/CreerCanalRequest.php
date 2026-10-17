<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreerCanalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'pays' => ['sometimes', 'nullable', 'regex:/^[A-Z]{2}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom du canal est obligatoire.',
            'nom.max' => 'Le nom du canal ne peut pas dépasser 50 caractères.',
            'description.max' => 'La description ne peut pas dépasser 300 caractères.',
            'pays.regex' => 'Le pays doit être un code ISO à deux lettres.',
        ];
    }
}
