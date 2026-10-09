<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mission extends Model
{
    protected $fillable = [
        'client_id', 'auteur_id', 'app_id', 'message',
        'budget', 'delai', 'statut', 'note', 'note_commentaire', 'terminee_le',
    ];

    protected function casts(): array
    {
        return [
            'delai' => 'date',
            'budget' => 'integer',
            'note' => 'integer',
            'terminee_le' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MissionMessage::class);
    }

    // Seuls le client et l'auteur voient une mission.
    public function concerne(User $user): bool
    {
        return $user->id === $this->client_id || $user->id === $this->auteur_id;
    }
}
