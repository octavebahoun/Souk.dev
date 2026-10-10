<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Difficulte;
use App\Enums\Etiquette;
use App\Enums\Techno;
use App\Rules\AppPubliee;
use App\Rules\DepotGithub;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CreerDiscussionRequest extends FormRequest
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
            'texte' => ['required', 'string'],
            'canal' => ['required_without:app_id', 'prohibits:app_id', 'string', 'exists:canaux,slug'],
            'app_id' => ['required_without:canal', 'prohibits:canal', 'integer', new AppPubliee],
            'depot_url' => ['sometimes', 'nullable', 'string', new DepotGithub],
            'demo_url' => ['sometimes', 'nullable', 'url'],
            'etiquettes' => ['sometimes', 'array'],
            'etiquettes.*' => ['string', Rule::enum(Etiquette::class)],
            'bug' => ['sometimes', 'array'],
            'bug.erreur_obtenue' => ['required_with:bug', 'string', 'max:2000'],
            'bug.comportement_attendu' => ['required_with:bug', 'string', 'max:2000'],
            'bug.techno' => ['sometimes', 'nullable', Rule::enum(Techno::class)],
            'bug.code' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'bug.difficulte' => ['sometimes', 'nullable', Rule::enum(Difficulte::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $etiquettes = $this->input('etiquettes', []);

            if (is_array($etiquettes) && in_array('bug', $etiquettes, true) && ! is_array($this->input('bug'))) {
                $validator->errors()->add('bug', 'La fiche du bug est obligatoire pour cette étiquette.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titre.required' => 'Le titre est obligatoire.',
            'titre.max' => 'Le titre ne peut pas dépasser 200 caractères.',
            'texte.required' => 'Le texte est obligatoire.',
            'canal.required_without' => 'Indiquez un canal ou une appli.',
            'canal.prohibits' => 'Une discussion est dans un canal ou sur une appli, pas les deux.',
            'canal.exists' => 'Ce canal n\'existe pas.',
            'app_id.required_without' => 'Indiquez un canal ou une appli.',
            'app_id.prohibits' => 'Une discussion est dans un canal ou sur une appli, pas les deux.',
            'demo_url.url' => 'L\'adresse de démo est invalide.',
            'etiquettes.*.enum' => 'Étiquette inconnue.',
            'bug.erreur_obtenue.required_with' => 'Décrivez l\'erreur obtenue.',
            'bug.comportement_attendu.required_with' => 'Décrivez le comportement attendu.',
        ];
    }
}
