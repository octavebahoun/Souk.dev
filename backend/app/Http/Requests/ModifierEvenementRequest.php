<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Evenement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ModifierEvenementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $evenement = $this->route('evenement');

        return $evenement instanceof Evenement && ($this->user()?->can('update', $evenement) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titre' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'string', 'max:10000'],
            'date' => ['sometimes', 'date', 'after:now'],
            'type' => ['sometimes', Rule::in(['en_ligne', 'presentiel'])],
            'lieu' => ['sometimes', 'nullable', 'string', 'max:200'],
            'lien' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $evenement = $this->route('evenement');

            if (! $evenement instanceof Evenement) {
                return;
            }

            $type = $this->input('type', $evenement->type);
            $lieu = $this->exists('lieu') ? $this->input('lieu') : $evenement->lieu;
            $lien = $this->exists('lien') ? $this->input('lien') : $evenement->lien;

            if ($type === 'presentiel' && ($lieu === null || $lieu === '')) {
                $validator->errors()->add('lieu', 'Le lieu est obligatoire pour un événement en présentiel.');
            }

            if ($type === 'en_ligne' && ($lien === null || $lien === '')) {
                $validator->errors()->add('lien', 'Le lien est obligatoire pour un événement en ligne.');
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
            'date.date' => 'La date est invalide.',
            'type.in' => 'Le type doit être en_ligne ou presentiel.',
            'lien.url' => 'Le lien est invalide.',
        ];
    }
}
