<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LienMagique extends Model
{
    protected $table = 'liens_magiques';

    /**
     * @var list<string>
     */
    protected $fillable = [
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function compte(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
