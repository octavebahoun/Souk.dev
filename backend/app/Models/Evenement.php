<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evenement extends Model
{
    protected $table = 'evenements';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'titre',
        'description',
        'date',
        'type',
        'lieu',
        'lien',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function organisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<Inscription, $this>
     */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /**
     * @return HasMany<Avis, $this>
     */
    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class);
    }
}
