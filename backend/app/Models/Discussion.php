<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DiscussionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discussion extends Model
{
    /** @use HasFactory<DiscussionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'canal_id',
        'app_id',
        'titre',
        'texte',
        'depot_url',
        'demo_url',
        'etiquettes',
        'bug',
        'resolue',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'etiquettes' => 'array',
            'bug' => 'array',
            'resolue' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Canal, $this>
     */
    public function canal(): BelongsTo
    {
        return $this->belongsTo(Canal::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
