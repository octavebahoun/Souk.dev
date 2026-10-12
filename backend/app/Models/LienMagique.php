<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LienMagique extends Model
{
    protected $table = 'liens_magiques';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'token_hash',
        'expire_le',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expire_le' => 'datetime',
            'utilise_le' => 'datetime',
        ];
    }
}
