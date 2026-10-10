<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Difficulte;
use App\Enums\Etiquette;
use App\Enums\Techno;
use App\Models\Discussion;
use App\Rules\DepotGithub;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ModifierDiscussionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $discussion = $this->route('discussion');

        return $discussion instanceof Discussion && $this->user()?->can('update', $discussion) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titre' => ['sometimes', 'string', 'max:200'],
            'texte' => ['sometimes', 'string'],
            'depot_url' => ['sometimes', 'nullable', 'string', new DepotGithub],
            'demo_url' => ['sometimes', 'nullable', 'url'],
            'etiquettes' => ['sometimes', 'array'],
            'etiquettes.*' => ['string', Rule::enum(Etiquette::class)],
            'resolue' => ['sometimes', 'boolean'],
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
            $discussion = $this->route('discussion');

            if (! $discussion instanceof Discussion) {
                return;
            }

            $etiquettes = $this->exists('etiquettes')
                ? $this->input('etiquettes', [])
                : ($discussion->etiquettes ?? []);

            if (! is_array($etiquettes) || ! in_array('bug', $etiquettes, true)) {
                return;
            }

            $bug = $this->exists('bug') ? $this->input('bug') : $discussion->bug;

            if (! is_array($bug)) {
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
            'titre.max' => 'Le titre ne peut pas dépasser 200 caractères.',
            'demo_url.url' => 'L\'adresse de démo est invalide.',
            'etiquettes.*.enum' => 'Étiquette inconnue.',
            'bug.erreur_obtenue.required_with' => 'Décrivez l\'erreur obtenue.',
            'bug.comportement_attendu.required_with' => 'Décrivez le comportement attendu.',
        ];
    }
}
