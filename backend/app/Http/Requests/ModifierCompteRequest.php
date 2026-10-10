<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Specialite;
use App\Enums\Techno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ModifierCompteRequest extends FormRequest
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
            'pays' => ['sometimes', 'nullable', 'regex:/^[A-Z]{2}$/'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'competences' => ['sometimes', 'array', 'max:10'],
            'competences.*' => ['string', 'distinct', Rule::enum(Techno::class)],
            'specialites' => ['sometimes', 'array'],
            'specialites.*' => ['string', 'distinct', Rule::enum(Specialite::class)],
            'disponible' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pays.regex' => 'Le pays doit être un code ISO à deux lettres.',
            'bio.max' => 'La bio ne peut pas dépasser 500 caractères.',
            'competences.max' => 'Dix compétences au maximum.',
            'competences.*.enum' => 'Compétence inconnue.',
            'competences.*.distinct' => 'Cette compétence est en double.',
            'specialites.*.enum' => 'Spécialité inconnue.',
            'specialites.*.distinct' => 'Cette spécialité est en double.',
            'disponible.boolean' => 'La disponibilité doit être vrai ou faux.',
        ];
    }
}
