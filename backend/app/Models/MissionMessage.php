<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionMessage extends Model
{
    protected $fillable = ['mission_id', 'auteur_id', 'texte'];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
