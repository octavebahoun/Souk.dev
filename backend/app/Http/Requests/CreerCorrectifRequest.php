<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\DepotGithub;
use Illuminate\Foundation\Http\FormRequest;

final class CreerCorrectifRequest extends FormRequest
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
            'depot_url' => ['required', 'string', new DepotGithub],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'demo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'depot_url.required' => 'L\'adresse du dépôt est obligatoire.',
            'description.max' => 'La description ne peut pas dépasser 1000 caractères.',
            'demo_url.url' => 'L\'adresse de démo est invalide.',
        ];
    }
}
