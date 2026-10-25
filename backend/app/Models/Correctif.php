<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Correctif extends Model
{
    protected $table = 'correctifs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'discussion_id',
        'user_id',
        'depot_url',
        'demo_url',
        'description',
        'accepte',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepte' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Discussion, $this>
     */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Dernier déploiement de la version corrigée, null si son auteur n'en a jamais lancé.
     *
     * @return HasOne<Deploiement, $this>
     */
    public function deploiement(): HasOne
    {
        return $this->hasOne(Deploiement::class)->where('type', 'correctif')->latestOfMany();
    }
}
