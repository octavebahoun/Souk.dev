<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreerEvenementRequest extends FormRequest
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
            'titre' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(['en_ligne', 'presentiel'])],
            'lieu' => ['required_if:type,presentiel', 'nullable', 'string', 'max:200'],
            'lien' => ['required_if:type,en_ligne', 'nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titre.required' => 'Le titre est obligatoire.',
            'titre.max' => 'Le titre ne peut pas dépasser 200 caractères.',
            'description.required' => 'La description est obligatoire.',
            'date.required' => 'La date est obligatoire.',
            'date.date' => 'La date est invalide.',
            'type.required' => 'Le type est obligatoire.',
            'type.in' => 'Le type doit être en_ligne ou presentiel.',
            'lieu.required_if' => 'Le lieu est obligatoire pour un événement en présentiel.',
            'lien.required_if' => 'Le lien est obligatoire pour un événement en ligne.',
            'lien.url' => 'Le lien est invalide.',
        ];
    }
}
