<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Request;

final class Pagination
{
    public static function parPage(Request $request): int
    {
        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ], [
            'page.min' => 'La page commence à 1.',
            'per_page.min' => 'Le nombre par page doit être entre 1 et 50.',
            'per_page.max' => 'Le nombre par page doit être entre 1 et 50.',
        ]);

        return $request->integer('per_page', 20) ?: 20;
    }
}
