<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AppPubliee implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $id = is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : null);

        if ($id === null || ! Schema::hasTable('apps') || ! DB::table('apps')->where('id', $id)->exists()) {
            $fail('Cette appli n\'existe pas.');
        }
    }
}
